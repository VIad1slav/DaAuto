<?php
/**
 * DaAuto — приём заявок с сайта.
 *
 * Сохраняет заявку в data/store.php и отправляет карточку с кнопками статуса
 * во все подключённые чаты Telegram (см. bot.php и README.md).
 * Токен бота остаётся на сервере и не виден посетителям сайта.
 */
define('DAAUTO', 1);
require __DIR__ . '/lib/core.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function reply(int $code, array $data): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    reply(405, ['ok' => false, 'error' => 'method']);
}
if (cfg('bot_token') === '') {
    reply(500, ['ok' => false, 'error' => 'not_configured']);
}

/* ---------- запрос только с нашего сайта ---------- */
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    $originHost = parse_url($origin, PHP_URL_HOST);
    $ownHost    = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? '');
    if ($originHost !== $ownHost) {
        reply(403, ['ok' => false, 'error' => 'origin']);
    }
}

/* ---------- данные формы (JSON или обычная форма) ---------- */
$raw  = file_get_contents('php://input', false, null, 0, 20000);
$data = json_decode($raw ?: '', true);
if (!is_array($data)) {
    $data = $_POST;
}
$get = static function (string $key, int $max) use ($data): string {
    $v = isset($data[$key]) && is_scalar($data[$key]) ? trim((string)$data[$key]) : '';
    $v = preg_replace('/\s+/u', ' ', $v);
    return mb_substr($v, 0, $max);
};

// Ловушка для ботов: скрытое поле, человек его не заполняет.
if ($get('website', 100) !== '') {
    reply(200, ['ok' => true]);
}

$fields = [
    'name'      => $get('name', 100),
    'phone'     => $get('phone', 40),
    'service'   => $get('service', 40),
    'language'  => $get('language', 5),
    'page_lang' => $get('page_lang', 5),
    'message'   => mb_substr(trim(isset($data['message']) && is_scalar($data['message']) ? (string)$data['message'] : ''), 0, 1500),
];

$digits = preg_replace('/\D/', '', $fields['phone']);
if (mb_strlen($fields['name']) < 2 || strlen($digits) < 9 || strlen($digits) > 15) {
    reply(422, ['ok' => false, 'error' => 'validation']);
}

/* ---------- не больше 5 заявок с одного IP за 10 минут ----------
   Считаются только доставленные заявки: неудачные попытки (ошибка настройки,
   нет связи с Telegram) лимит не расходуют. */
$ip     = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rlFile = sys_get_temp_dir() . '/daauto_rl_' . md5($ip);
$now    = time();
$hits   = [];
$rlOff  = getenv('DAAUTO_NO_RATE_LIMIT') === '1'; // только для проверки на компьютере (dev/)
if (is_file($rlFile)) {
    $hits = array_filter(
        array_map('intval', explode(',', (string)file_get_contents($rlFile))),
        static function ($t) use ($now) { return $t > $now - 600; }
    );
}
if (!$rlOff && count($hits) >= 5) {
    reply(429, ['ok' => false, 'error' => 'rate_limit']);
}

/* ---------- сохранить заявку ---------- */
$stored = true;
try {
    $to = recipients(db_read());
    if (!$to) {
        reply(500, ['ok' => false, 'error' => 'no_recipients']);
    }
    $lead = db_update(static function (array &$db) use ($fields, $now): array {
        $lead = $fields + [
            'id'        => (int)$db['next_id'],
            'created'   => $now,
            'status'    => 'new',
            'by'        => '',
            'by_id'     => 0,
            'status_at' => $now,
            'history'   => [],
            'msgs'      => [],
        ];
        $db['next_id'] = $lead['id'] + 1;
        $db['leads'][] = $lead;
        return $lead;
    });
} catch (Throwable $e) {
    // Хранилище недоступно (нет прав на запись в data/ и т. п.) — заявку всё равно
    // не теряем: шлём простую карточку без кнопок в чат из config.php.
    error_log('DaAuto send.php: storage error: ' . $e->getMessage());
    $stored = false;
    $to     = cfg('chat_id') !== '' ? [cfg('chat_id')] : [];
    $lead   = $fields + ['id' => 0, 'created' => $now, 'status' => 'new'];
    if (!$to) {
        reply(500, ['ok' => false, 'error' => 'storage']);
    }
}

/* ---------- отправить в Telegram ---------- */
$sent = [];
$gone = [];
foreach ($to as $chat) {
    $r = send_card($chat, $lead);
    if (!empty($r['ok'])) {
        $sent[] = [(string)$chat, (int)$r['result']['message_id']];
    } elseif ((int)($r['error_code'] ?? 0) === 403 && (string)$chat !== cfg('chat_id')) {
        $gone[] = (string)$chat; // бота заблокировали или удалили из группы
    }
}

if ($stored && ($sent || $gone)) {
    try {
        db_update(static function (array &$db) use ($lead, $sent, $gone): void {
            foreach ($gone as $c) {
                unset($db['subs'][$c]);
            }
            $i = lead_index($db, (int)$lead['id']);
            if ($i !== null) {
                $db['leads'][$i]['msgs'] = $sent;
            }
        });
    } catch (Throwable $e) {
        error_log('DaAuto send.php: storage error: ' . $e->getMessage());
    }
}

if (!$sent) {
    reply(502, ['ok' => false, 'error' => 'telegram']);
}
if (!$rlOff) {
    $hits[] = $now;
    @file_put_contents($rlFile, implode(',', $hits), LOCK_EX);
}
reply(200, ['ok' => true]);
