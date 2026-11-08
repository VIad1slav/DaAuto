<?php
/**
 * DaAuto — проверка Telegram-бота на этом компьютере, без хостинга.
 * Запускается из dev\test-bot.bat (двойной щелчок).
 *
 * 1. Поднимает сайт на http://localhost:8090 (встроенный сервер PHP).
 * 2. Забирает из Telegram новые сообщения и нажатия кнопок (getUpdates)
 *    и передаёт их в bot.php — так же, как Telegram сам делает на хостинге.
 *
 * На хостинг эта папка не нужна.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
define('DAAUTO', 1);
$site = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'daauto-site';
require $site . '/lib/core.php';

// Технические ошибки — в лог, а не в окно.
ini_set('error_log', __DIR__ . DIRECTORY_SEPARATOR . 'php-server.log');

$port = (int)(getenv('DAAUTO_PORT') ?: 8090);
$url  = 'http://localhost:' . $port;

function out(string $s = ''): void
{
    echo $s, PHP_EOL;
}

function fail(string $s): void
{
    out();
    out('❌ ' . $s);
    exit(1);
}

function port_busy(int $port): bool
{
    $s = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.3);
    if ($s) {
        fclose($s);
        return true;
    }
    return false;
}

out('DaAuto — проверка Telegram-бота на этом компьютере');
out(str_repeat('─', 52));

/* ---------- настройки ---------- */
if (!is_file($site . '/config.php') && cfg('bot_token') === '') {
    fail("Нет файла daauto-site\\config.php.\n"
        . "   Скопируйте config.example.php в config.php и впишите bot_token, access_code и webhook_secret.");
}
foreach (['bot_token', 'access_code', 'webhook_secret'] as $k) {
    if (cfg($k) === '') {
        fail("В daauto-site\\config.php пустое поле $k — заполните его (подсказки в самом файле).");
    }
}

/* ---------- связь с Telegram ---------- */
$me = tg('getMe');
if (empty($me['ok'])) {
    $code = (int)($me['error_code'] ?? 0);
    if ($code === 401 || $code === 404) {
        fail('Telegram не принял токен — проверьте bot_token в config.php (его даёт @BotFather).');
    }
    fail('Нет связи с Telegram: ' . ($me['description'] ?? '') . "\n"
        . '   Проверьте интернет. Если Telegram у вас открывается только через VPN — включите VPN.');
}
$username = (string)$me['result']['username'];

/* ---------- если бот уже подключён к сайту на хостинге ---------- */
$info = tg('getWebhookInfo');
$hook = (string)($info['result']['url'] ?? '');
if ($hook !== '') {
    out("⚠️  Бот сейчас работает на сайте: $hook");
    out('   Пока идёт проверка здесь, кнопки и команды на хостинге работать не будут.');
    out('   После проверки откройте на хостинге setup.php?key=... — это вернёт всё как было.');
    out();
    echo '   Продолжить? Введите y и нажмите Enter: ';
    $answer = strtolower(trim((string)fgets(STDIN)));
    if ($answer !== 'y' && $answer !== 'д') {
        fail('Отменено, ничего не изменилось.');
    }
    tg('deleteWebhook');
}

/* ---------- сайт на localhost ---------- */
if (port_busy($port)) {
    fail("Порт $port уже занят — возможно, проверка уже запущена в другом окне. Закройте его и запустите снова.");
}
$args = [PHP_BINARY];
foreach (['extension_dir', 'curl.cainfo', 'openssl.cafile'] as $k) {
    $v = (string)ini_get($k);
    if ($v !== '') {
        $args[] = '-d';
        $args[] = $k . '=' . $v;
    }
}
// router.php — белый список файлов сайта (php -S не читает .htaccess).
$args = array_merge($args, ['-S', '127.0.0.1:' . $port, '-t', $site, __DIR__ . DIRECTORY_SEPARATOR . 'router.php']);

/** Запускает сервер сайта и ждёт, пока он начнёт отвечать. */
function start_site(array $args, int $port): bool
{
    global $proc, $pipes;
    $log  = __DIR__ . DIRECTORY_SEPARATOR . 'php-server.log';
    $proc = proc_open($args, [0 => ['pipe', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes);
    if (!is_resource($proc)) {
        return false;
    }
    for ($i = 0; $i < 50 && !port_busy($port); $i++) {
        usleep(100000);
    }
    return port_busy($port);
}

$proc  = null;
$pipes = [];
register_shutdown_function(static function (): void {
    global $proc;
    if (is_resource($proc)) {
        @proc_terminate($proc);
    }
});
if (!start_site($args, $port)) {
    fail('Сайт не запустился — подробности в dev\php-server.log');
}

/** Если сервер сайта сам остановился — поднимаем заново, чтобы страница открывалась. */
function keep_site_alive(array $args, int $port): void
{
    global $proc;
    if (is_resource($proc) && (proc_get_status($proc)['running'] ?? false)) {
        return;
    }
    out(date('H:i:s') . '  ⚠️  сайт остановился — запускаю снова');
    if (!port_busy($port) && start_site($args, $port)) {
        out(date('H:i:s') . '  сайт снова открывается');
    }
}

db_update(static function (array &$db) use ($username): void {
    $db['bot_username'] = $username;
});
tg('setMyCommands', ['commands' => bot_commands()]);

/* ---------- подсказка ---------- */
$links = invite_links($username);
out("✅ Бот @$username на связи, сайт открыт: $url/?lang=ru");
out();
out('Что проверить:');
out('  1. Подключитесь к заявкам — откройте ссылку и нажмите «Старт»:');
out('       ' . ($links['private'] ?? "напишите боту @$username: /start <код из config.php>"));
if (isset($links['group'])) {
    out('     или добавьте бота в группу: ' . $links['group']);
}
out('  2. Отправьте заявку с сайта — в Telegram придёт карточка.');
out('  3. Нажмите «Беру в работу», затем «Сделка»; попробуйте /new, /work, /stats.');
out();
out('Тестовые заявки хранятся в dev\data и на сайт не попадают.');
out('Остановить — закройте это окно.');
out(str_repeat('─', 52));

if (!getenv('DAAUTO_NO_BROWSER')) {
    pclose(popen('start "" "' . $url . '/?lang=ru"', 'r'));
}

/* ---------- передача событий из Telegram в bot.php ---------- */
function describe(array $u): string
{
    if (isset($u['callback_query'])) {
        $q      = $u['callback_query'];
        $labels = ['work' => '«Беру в работу»', 'done' => '«Сделка»', 'lost' => '«Отказ»', 'new' => '«В новые»'];
        $data   = (string)($q['data'] ?? '');
        $label  = preg_match('~^s:(\d+):(\w+)$~', $data, $m) ? ($labels[$m[2]] ?? $m[2]) . ' в заявке №' . $m[1] : $data;
        return user_name($q['from'] ?? []) . ' нажимает ' . $label;
    }
    $m    = $u['message'] ?? [];
    $text = (string)($m['text'] ?? '(не текст)');
    if (stripos($text, '/start') === 0 && strpos($text, ' ') !== false) {
        $text = '/start ••••••'; // код доступа в окно не выводим
    }
    $where = ($m['chat']['type'] ?? '') === 'private' ? '' : ' [' . ($m['chat']['title'] ?? 'группа') . ']';
    return user_name($m['from'] ?? []) . $where . ': ' . mb_substr($text, 0, 60);
}

function forward(array $u, int $port): int
{
    $ch = curl_init('http://127.0.0.1:' . $port . '/bot.php');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($u, JSON_UNESCAPED_UNICODE),
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'X-Telegram-Bot-Api-Secret-Token: ' . cfg('webhook_secret')],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 60,
    ]);
    curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return $code;
}

$offset   = 0;
$maxPolls = (int)(getenv('DAAUTO_MAX_POLLS') ?: 0); // только для автотестов
$polls    = 0;
$offline  = false;
$conflict = false;
// Сбои бота не должны останавливать сайт: при выходе из этого цикла
// закрывается и сервер на localhost, и страница перестаёт открываться.
while ($maxPolls === 0 || $polls++ < $maxPolls) {
    keep_site_alive($args, $port);
    $r = tg('getUpdates', [
        'offset'          => $offset,
        'timeout'         => $maxPolls ? 1 : 25,
        'allowed_updates' => ['message', 'callback_query'],
    ], 40);
    if (empty($r['ok'])) {
        if ((int)($r['error_code'] ?? 0) === 409) {
            // События забирает кто-то другой: второе окно проверки или бот подключён к сайту (setup.php).
            if (!$conflict) {
                out(date('H:i:s') . '  ⚠️  Telegram отдаёт события другому получателю — второе окно проверки');
                out('          или бот подключён к сайту на хостинге (setup.php). Сайт работает дальше,');
                out('          бот подождёт и подхватит события, когда второй получатель отключится.');
                $conflict = true;
            }
            sleep(10);
            continue;
        }
        if (!$offline) {
            out(date('H:i:s') . '  … нет связи с Telegram, пробую снова');
            $offline = true;
        }
        sleep(5);
        continue;
    }
    if ($offline || $conflict) {
        out(date('H:i:s') . '  связь восстановлена, бот снова получает события');
        $offline  = false;
        $conflict = false;
    }
    foreach ((array)($r['result'] ?? []) as $u) {
        $offset = (int)($u['update_id'] ?? 0) + 1;
        try {
            out(date('H:i:s') . '  ' . describe($u));
            $code = forward($u, $port);
            if ($code !== 200) {
                out("          ⚠️  bot.php ответил $code — подробности в dev\\php-server.log");
            }
        } catch (Throwable $e) {
            // Одно странное событие не должно ронять бота вместе с сайтом.
            error_log('DaAuto poll: ' . $e->getMessage());
            out(date('H:i:s') . '  ⚠️  не удалось обработать событие — подробности в dev\\php-server.log');
        }
    }
}
