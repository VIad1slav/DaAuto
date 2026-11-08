<?php
/**
 * DaAuto — общие функции бота заявок.
 * Подключается из send.php, bot.php и setup.php; сам по себе ничего не делает.
 *
 * Совместимо с PHP 7.4+. Без сторонних библиотек и без базы данных:
 * заявки и список подключённых чатов лежат в data/store.php (JSON).
 */
if (!defined('DAAUTO')) {
    http_response_code(404);
    exit;
}

date_default_timezone_set('Europe/Warsaw');
mb_internal_encoding('UTF-8');

const DA_SERVICES = [
    'europe'       => 'Покупка авто у европейского дилера',
    'selection'    => 'Автоподбор по Польше',
    'inspection'   => 'Техосмотр',
    'registration' => 'Регистрация и номера (Варшава)',
    'delivery'     => 'Доставка по Европе',
    'insurance'    => 'Страхование',
];
const DA_LANGS = ['ru' => 'русский', 'pl' => 'польский'];
const DA_STATUSES = [
    'new'  => ['🆕', 'Новая'],
    'work' => ['🙋', 'В работе'],
    'done' => ['✅', 'Сделка'],
    'lost' => ['❌', 'Отказ'],
];

/* =========================================================
   Настройки: config.php, затем переменные окружения
   ========================================================= */
function cfg(string $key, string $default = ''): string
{
    static $c = null;
    if ($c === null) {
        $file = dirname(__DIR__) . '/config.php';
        $c = is_file($file) ? (require $file) : [];
        if (!is_array($c)) {
            $c = [];
        }
    }
    $env = [
        'bot_token'      => 'TG_BOT_TOKEN',
        'chat_id'        => 'TG_CHAT_ID',
        'access_code'    => 'TG_ACCESS_CODE',
        'webhook_secret' => 'TG_WEBHOOK_SECRET',
        'api_base'       => 'TG_API_BASE',
    ];
    $v = isset($c[$key]) && is_scalar($c[$key]) ? trim((string)$c[$key]) : '';
    if ($v === '' && isset($env[$key])) {
        $v = trim((string)(getenv($env[$key]) ?: ''));
    }
    return $v !== '' ? $v : $default;
}

function h(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function fmt_time(int $ts, string $format = 'd.m.Y H:i'): string
{
    return date($format, $ts);
}

/* =========================================================
   Telegram Bot API
   ========================================================= */
function tg(string $method, array $params = [], int $timeout = 10): array
{
    $url  = rtrim(cfg('api_base', 'https://api.telegram.org'), '/') . '/bot' . cfg('bot_token') . '/' . $method;
    $body = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    $res  = false;
    $code = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => $timeout,
        ]);
        $res  = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/json\r\n",
            'content'       => $body,
            'timeout'       => $timeout,
            'ignore_errors' => true,
        ]]);
        $res = @file_get_contents($url, false, $ctx);
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
            $code = (int)$m[1];
        }
    }

    $out = json_decode((string)$res, true);
    if (!is_array($out)) {
        $out = ['ok' => false, 'error_code' => $code, 'description' => 'no response' . (!empty($cerr) ? ': ' . $cerr : '')];
    }
    $desc = (string)($out['description'] ?? '');
    if (empty($out['ok']) && strpos($desc, 'not modified') === false) {
        // Токен в лог не пишем — только метод и ответ Telegram.
        error_log('DaAuto bot: ' . $method . ' -> ' . ($out['error_code'] ?? $code) . ' ' . mb_substr($desc, 0, 200));
    }
    return $out;
}

function say($chat, string $text, array $extra = []): array
{
    return tg('sendMessage', $extra + [
        'chat_id'                  => $chat,
        'text'                     => $text,
        'parse_mode'               => 'HTML',
        'disable_web_page_preview' => true,
    ]);
}

/* =========================================================
   Хранилище: data/store.php
   Первая строка — PHP-заглушка, поэтому файл нельзя скачать
   даже на хостинге, который не читает .htaccess (nginx).
   ========================================================= */
const DA_GUARD = "<?php http_response_code(404); exit; ?>\n";

function db_file(): string
{
    // DAAUTO_DATA_DIR задаёт только проверка на компьютере (dev/), чтобы тестовые
    // заявки не попадали в папку сайта. На хостинге её нет — используется data/.
    $dir = getenv('DAAUTO_DATA_DIR') ?: dirname(__DIR__) . '/data';
    return rtrim($dir, '/\\') . '/store.php';
}

function db_blank(): array
{
    return ['next_id' => 1, 'leads' => [], 'subs' => [], 'fails' => [], 'bot_username' => ''];
}

/** @return resource */
function db_open()
{
    $dir = dirname(db_file());
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        throw new RuntimeException('cannot create data/');
    }
    if (!is_file($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess', "Require all denied\n");
    }
    $fh = @fopen(db_file(), 'c+');
    if (!$fh) {
        throw new RuntimeException('cannot open data/store.php');
    }
    return $fh;
}

/** @param resource $fh */
function db_parse($fh): array
{
    rewind($fh);
    $raw = (string)stream_get_contents($fh);
    if (trim($raw) === '') {
        return db_blank();
    }
    if (strpos($raw, DA_GUARD) === 0) {
        $raw = substr($raw, strlen(DA_GUARD));
    }
    $db = json_decode($raw, true);
    if (!is_array($db)) {
        // Не перезаписываем повреждённый файл пустым — иначе пропадут все заявки.
        throw new RuntimeException('data/store.php is corrupt');
    }
    return array_merge(db_blank(), $db);
}

function db_read(): array
{
    $fh = db_open();
    flock($fh, LOCK_SH);
    try {
        return db_parse($fh);
    } finally {
        flock($fh, LOCK_UN);
        fclose($fh);
    }
}

/**
 * Прочитать, изменить и записать хранилище под одной блокировкой.
 * $fn получает массив по ссылке: function (array &$db) { ... }
 */
function db_update(callable $fn)
{
    $fh = db_open();
    flock($fh, LOCK_EX);
    try {
        $db     = db_parse($fh);
        $result = $fn($db);
        $json   = json_encode($db, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($json === false) {
            throw new RuntimeException('cannot encode store');
        }
        rewind($fh);
        ftruncate($fh, 0);
        fwrite($fh, DA_GUARD . $json);
        fflush($fh);
        return $result;
    } finally {
        flock($fh, LOCK_UN);
        fclose($fh);
    }
}

function lead_index(array $db, int $id): ?int
{
    foreach ($db['leads'] as $i => $l) {
        if ((int)($l['id'] ?? 0) === $id) {
            return $i;
        }
    }
    return null;
}

/** Куда слать заявки: чат из config.php (если задан) + все, кто подключился через /start. */
function recipients(array $db): array
{
    $ids = array_map('strval', array_keys($db['subs']));
    if (cfg('chat_id') !== '') {
        array_unshift($ids, cfg('chat_id'));
    }
    return array_values(array_unique($ids));
}

function chat_allowed(array $db, $chatId): bool
{
    $chatId = (string)$chatId;
    return (cfg('chat_id') !== '' && $chatId === cfg('chat_id')) || isset($db['subs'][$chatId]);
}

function user_name(array $from): string
{
    $name = trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? ''));
    if ($name === '' && !empty($from['username'])) {
        $name = '@' . $from['username'];
    }
    return $name !== '' ? mb_substr($name, 0, 60) : 'id ' . ($from['id'] ?? '?');
}

/* =========================================================
   Карточка заявки
   ========================================================= */
function service_name(string $code): string
{
    return DA_SERVICES[$code] ?? ($code !== '' ? $code : '—');
}

function status_line(array $l): string
{
    $st = DA_STATUSES[$l['status'] ?? 'new'] ?? DA_STATUSES['new'];
    if (($l['status'] ?? 'new') === 'new') {
        return $st[0] . ' <b>Статус:</b> новая — ждёт менеджера';
    }
    return $st[0] . ' <b>' . $st[1] . ':</b> ' . h((string)($l['by'] ?? '')) . ' · ' . fmt_time((int)$l['status_at'], 'd.m H:i');
}

function lead_text(array $l): string
{
    $lines = [
        '🚗 <b>Заявка №' . (int)$l['id'] . '</b> с сайта DaAuto',
        '',
        '👤 <b>Имя:</b> ' . h($l['name']),
        '📞 <b>Телефон:</b> ' . h($l['phone']),
        '🛠 <b>Услуга:</b> ' . h(service_name((string)$l['service'])),
    ];
    if (($l['language'] ?? '') !== '') {
        $lines[] = '🗣 <b>Язык общения:</b> ' . h(DA_LANGS[$l['language']] ?? $l['language']);
    } elseif (($l['page_lang'] ?? '') !== '') {
        $lines[] = '🗣 <b>Язык сайта:</b> ' . h(DA_LANGS[$l['page_lang']] ?? $l['page_lang']);
    }
    if (($l['message'] ?? '') !== '') {
        $lines[] = '💬 <b>Комментарий:</b> ' . h($l['message']);
    }
    $lines[] = '';
    $lines[] = '🕒 ' . fmt_time((int)$l['created']);
    if ((int)$l['id'] > 0) {
        $lines[] = status_line($l);
    }
    return implode("\n", $lines);
}

/** Ссылка на WhatsApp; номер из 9 цифр считаем польским (+48). */
function wa_link(string $phone): string
{
    $d = preg_replace('/\D/', '', $phone);
    $d = preg_replace('/^00/', '', $d);
    if (strlen($d) === 9) {
        $d = '48' . $d;
    }
    return strlen($d) >= 10 && strlen($d) <= 15 ? 'https://wa.me/' . $d : '';
}

function lead_keyboard(array $l): array
{
    $rows = [];
    $id   = (int)$l['id'];
    if ($id > 0) {
        $b = static function (string $text, string $to) use ($id): array {
            return ['text' => $text, 'callback_data' => 's:' . $id . ':' . $to];
        };
        switch ($l['status']) {
            case 'work':
                $rows[] = [$b('✅ Сделка', 'done'), $b('❌ Отказ', 'lost'), $b('↩️ В новые', 'new')];
                break;
            case 'done':
            case 'lost':
                $rows[] = [$b('↩️ Вернуть в работу', 'work')];
                break;
            default:
                $rows[] = [$b('🙋 Беру в работу', 'work')];
        }
    }
    $wa = wa_link((string)$l['phone']);
    if ($wa !== '') {
        $rows[] = [['text' => '💬 Написать в WhatsApp', 'url' => $wa]];
    }
    return ['inline_keyboard' => $rows];
}

function send_card($chat, array $l): array
{
    return say($chat, lead_text($l), ['reply_markup' => lead_keyboard($l)]);
}

/** Обновить карточку заявки во всех чатах, куда она была отправлена. */
function refresh_cards(array $l): void
{
    foreach ($l['msgs'] ?? [] as $m) {
        tg('editMessageText', [
            'chat_id'                  => $m[0],
            'message_id'               => (int)$m[1],
            'text'                     => lead_text($l),
            'parse_mode'               => 'HTML',
            'disable_web_page_preview' => true,
            'reply_markup'             => lead_keyboard($l),
        ]);
    }
}

/** Короткая строка для списков /leads, /new, /work. */
function lead_brief(array $l): string
{
    $st   = DA_STATUSES[$l['status']] ?? DA_STATUSES['new'];
    $who  = $l['status'] !== 'new' && ($l['by'] ?? '') !== '' ? ' ' . h($l['by']) : '';
    return '<b>№' . (int)$l['id'] . '</b> · ' . fmt_time((int)$l['created'], 'd.m H:i') . ' · ' . h($l['name'])
        . ' · <code>' . h($l['phone']) . '</code>' . "\n"
        . '      ' . h(service_name((string)$l['service'])) . ' · ' . $st[0] . $who . ' · /lead_' . (int)$l['id'];
}

/** Меню команд бота (кнопка «Меню» в Telegram). */
function bot_commands(): array
{
    return [
        ['command' => 'new',   'description' => 'Новые заявки — ещё никто не взял'],
        ['command' => 'work',  'description' => 'Заявки в работе'],
        ['command' => 'leads', 'description' => 'Последние 10 заявок'],
        ['command' => 'stats', 'description' => 'Статистика'],
        ['command' => 'help',  'description' => 'Что умеет бот'],
        ['command' => 'stop',  'description' => 'Не присылать заявки в этот чат'],
    ];
}

/** Ссылки-приглашения для менеджеров (если код из допустимых символов). */
function invite_links(string $username): array
{
    $code = cfg('access_code');
    if ($username === '' || !preg_match('~^[A-Za-z0-9_-]{1,64}$~', $code)) {
        return [];
    }
    return [
        'private' => 'https://t.me/' . $username . '?start=' . $code,
        'group'   => 'https://t.me/' . $username . '?startgroup=' . $code,
    ];
}

function help_text(): string
{
    return implode("\n", [
        '<b>Что умеет бот</b>',
        'Новые заявки с сайта приходят сюда сами. Под заявкой — кнопки статуса: '
            . '«Беру в работу», потом «Сделка» или «Отказ». Статус обновляется у всех менеджеров.',
        '',
        '/new — новые, ещё никто не взял',
        '/work — в работе',
        '/leads — последние 10 заявок',
        '/lead_12 — открыть заявку №12',
        '/stats — статистика',
        '/stop — не присылать заявки в этот чат',
    ]);
}
