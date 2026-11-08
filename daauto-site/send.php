<?php
/**
 * DaAuto — отправка заявок с сайта в Telegram.
 *
 * Токен бота и chat_id берутся из config.php (образец — config.example.php)
 * или из переменных окружения TG_BOT_TOKEN / TG_CHAT_ID.
 * Токен остаётся на сервере и не виден посетителям сайта.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function reply(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    reply(405, ['ok' => false, 'error' => 'method']);
}

/* ---------- настройки ---------- */
$config = is_file(__DIR__ . '/config.php') ? (require __DIR__ . '/config.php') : [];
$token  = (string)(($config['bot_token'] ?? '') ?: (getenv('TG_BOT_TOKEN') ?: ''));
$chatId = (string)(($config['chat_id']   ?? '') ?: (getenv('TG_CHAT_ID')   ?: ''));
if ($token === '' || $chatId === '') {
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

$name    = $get('name', 100);
$phone   = $get('phone', 40);
$service = $get('service', 40);
$lang    = $get('language', 5);
$pageLng = $get('page_lang', 5);
$message = mb_substr(trim((string)($data['message'] ?? '')), 0, 1500);

$digits = preg_replace('/\D/', '', $phone);
if (mb_strlen($name) < 2 || strlen($digits) < 9 || strlen($digits) > 15) {
    reply(422, ['ok' => false, 'error' => 'validation']);
}

/* ---------- не больше 5 заявок с одного IP за 10 минут ---------- */
$ip     = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$rlFile = sys_get_temp_dir() . '/daauto_rl_' . md5($ip);
$now    = time();
$hits   = [];
if (is_file($rlFile)) {
    $hits = array_filter(
        array_map('intval', explode(',', (string)file_get_contents($rlFile))),
        static fn($t) => $t > $now - 600
    );
}
if (count($hits) >= 5) {
    reply(429, ['ok' => false, 'error' => 'rate_limit']);
}
$hits[] = $now;
@file_put_contents($rlFile, implode(',', $hits), LOCK_EX);

/* ---------- текст сообщения ---------- */
$services = [
    'europe'       => 'Покупка авто у европейского дилера',
    'selection'    => 'Автоподбор по Польше',
    'inspection'   => 'Техосмотр',
    'registration' => 'Регистрация и номера (Варшава)',
    'delivery'     => 'Доставка по Европе',
    'insurance'    => 'Страхование',
];
$langs = ['ru' => 'русский', 'pl' => 'польский'];
$e = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$lines = [
    '🚗 <b>Новая заявка с сайта DaAuto</b>',
    '',
    '👤 <b>Имя:</b> ' . $e($name),
    '📞 <b>Телефон:</b> ' . $e($phone),
    '🛠 <b>Услуга:</b> ' . $e($services[$service] ?? ($service !== '' ? $service : '—')),
];
if ($lang !== '') {
    $lines[] = '🗣 <b>Язык общения:</b> ' . $e($langs[$lang] ?? $lang);
} elseif ($pageLng !== '') {
    $lines[] = '🗣 <b>Язык сайта:</b> ' . $e($langs[$pageLng] ?? $pageLng);
}
if ($message !== '') {
    $lines[] = '💬 <b>Комментарий:</b> ' . $e($message);
}
$lines[] = '';
$lines[] = '🕒 ' . (new DateTime('now', new DateTimeZone('Europe/Warsaw')))->format('d.m.Y H:i');

$payload = [
    'chat_id'                  => $chatId,
    'text'                     => implode("\n", $lines),
    'parse_mode'               => 'HTML',
    'disable_web_page_preview' => true,
];

/* ---------- отправка в Telegram ---------- */
$url  = 'https://api.telegram.org/bot' . $token . '/sendMessage';
$body = json_encode($payload, JSON_UNESCAPED_UNICODE);

if (function_exists('curl_init')) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $res  = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
} else {
    $ctx = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => "Content-Type: application/json\r\n",
        'content'       => $body,
        'timeout'       => 10,
        'ignore_errors' => true,
    ]]);
    $res  = @file_get_contents($url, false, $ctx);
    $code = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $code = (int)$m[1];
    }
}

$tg = json_decode((string)$res, true);
if ($code !== 200 || empty($tg['ok'])) {
    error_log('DaAuto send.php: Telegram error ' . $code . ' ' . substr((string)$res, 0, 300));
    reply(502, ['ok' => false, 'error' => 'telegram']);
}

reply(200, ['ok' => true]);
