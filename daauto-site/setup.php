<?php
/**
 * DaAuto — подключение бота к Telegram (один раз после выкладки).
 *
 * Откройте в браузере: https://ваш-сайт/setup.php?key=<webhook_secret из config.php>
 * Скрипт проверит токен, подключит вебхук bot.php и меню команд,
 * и покажет ссылки, по которым менеджеры подключаются к заявкам.
 */
define('DAAUTO', 1);
require __DIR__ . '/lib/core.php';

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex');

$secret = cfg('webhook_secret');
if (cfg('bot_token') === '' || $secret === '' || cfg('access_code') === '') {
    exit("Заполните bot_token, access_code и webhook_secret в config.php.\n");
}
if (!preg_match('~^[A-Za-z0-9_-]{16,256}$~', $secret)) {
    exit("webhook_secret: только латиница, цифры, _ и -, не короче 16 символов.\n");
}
if (!hash_equals($secret, (string)($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit("Откройте setup.php?key=<webhook_secret из config.php>\n");
}

$me = tg('getMe');
if (empty($me['ok'])) {
    exit('❌ Telegram не принял токен: ' . ($me['description'] ?? 'нет ответа') . "\nПроверьте bot_token в config.php.\n");
}
$username = (string)$me['result']['username'];

try {
    db_update(static function (array &$db) use ($username): void {
        $db['bot_username'] = $username;
    });
} catch (Throwable $e) {
    exit('❌ Не получается записать в папку data/: ' . $e->getMessage()
        . "\nДайте PHP права на запись в папку data/ (обычно 755) и откройте страницу ещё раз.\n");
}

$path = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$url  = 'https://' . ($_SERVER['HTTP_HOST'] ?? '') . $path . '/bot.php';

$hook = tg('setWebhook', [
    'url'                  => $url,
    'secret_token'         => $secret,
    'allowed_updates'      => ['message', 'callback_query'],
    'drop_pending_updates' => true,
]);
if (empty($hook['ok'])) {
    exit('❌ Вебхук не подключён: ' . ($hook['description'] ?? 'нет ответа') . "\n"
        . "Адрес: $url\nTelegram работает только с сайтами на HTTPS с действующим сертификатом.\n");
}

tg('setMyCommands', ['commands' => bot_commands()]);

$info  = tg('getWebhookInfo');
$db    = db_read();
$links = invite_links($username);

echo "✅ Бот подключён: @$username\n";
echo "Вебхук: $url\n";
if (!empty($info['result']['last_error_message'])) {
    echo 'Последняя ошибка Telegram: ' . $info['result']['last_error_message'] . "\n";
}
echo "\nКак подключить менеджера:\n";
if ($links) {
    echo "  • себе в личку — открыть ссылку и нажать «Старт»:\n    {$links['private']}\n";
    echo "  • в группу — открыть ссылку и выбрать группу:\n    {$links['group']}\n";
} else {
    echo "  • написать боту @$username: /start <код доступа>\n";
    echo "    (если в коде только латиница, цифры, _ и -, здесь появятся готовые ссылки)\n";
}
echo "  Ссылки содержат код доступа — отправляйте их только сотрудникам.\n";

echo "\nСейчас подключено чатов: " . count($db['subs']) . "\n";
foreach ($db['subs'] as $id => $s) {
    echo '  • ' . $s['title'] . ' (id ' . $id . ', с ' . fmt_time((int)$s['since']) . ")\n";
}
if (cfg('chat_id') !== '') {
    echo '  • плюс чат из config.php: ' . cfg('chat_id') . "\n";
}
echo "\nЗаявок в базе: " . count($db['leads']) . "\n";
