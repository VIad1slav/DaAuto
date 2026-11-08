<?php
/**
 * Маршрутизатор для встроенного сервера PHP (php -S), запускается из poll.php.
 *
 * php -S не читает .htaccess, а на Windows ещё и понимает хитрые имена файлов:
 * /config.php::$DATA отдаёт исходный текст config.php вместе с токеном бота.
 * Поэтому здесь белый список: открываются только файлы самого сайта,
 * на всё остальное — 404. Особенно важно, когда сайт открыт наружу через туннель.
 *
 * setup.php в списке нет: при проверке на компьютере бот получает события
 * через getUpdates, а setup.php включил бы вебхук на адрес туннеля.
 */
$path = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$path = rawurldecode(is_string($path) ? $path : '');

if ($path === '/' || preg_match('~\A/(index\.html|styles\.css|script\.js|send\.php|bot\.php)\z~', $path)) {
    return false; // отдать файл (или выполнить .php) как обычно
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'Not found';
