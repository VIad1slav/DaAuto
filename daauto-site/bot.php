<?php
/**
 * DaAuto — вебхук Telegram-бота заявок.
 *
 * Telegram вызывает этот файл, когда менеджер пишет боту команду
 * или нажимает кнопку под заявкой. Подключается один раз через setup.php.
 */
define('DAAUTO', 1);
require __DIR__ . '/lib/core.php';

/* ---------- запрос должен прийти от Telegram ---------- */
$secret = cfg('webhook_secret');
$got    = (string)($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '');
if (cfg('bot_token') === '' || $secret === '' || !hash_equals($secret, $got)) {
    http_response_code(403);
    exit;
}

$update = json_decode((string)file_get_contents('php://input', false, null, 0, 100000), true);
http_response_code(200); // всегда 200, иначе Telegram будет повторять запрос
header('Content-Type: text/plain; charset=utf-8');
if (!is_array($update)) {
    exit;
}

try {
    if (isset($update['callback_query'])) {
        on_callback($update['callback_query']);
    } elseif (isset($update['message']['text'], $update['message']['chat']['id'])) {
        on_message($update['message']);
    }
} catch (Throwable $e) {
    error_log('DaAuto bot.php: ' . $e->getMessage());
}
exit;

/* =========================================================
   Команды
   ========================================================= */
function on_message(array $m): void
{
    $chat    = (string)$m['chat']['id'];
    $private = ($m['chat']['type'] ?? '') === 'private';
    $text    = trim((string)$m['text']);

    if (!preg_match('~^/([A-Za-z0-9_]+)(?:@([A-Za-z0-9_]+))?(?:\s+(.*))?$~s', $text, $mm)) {
        // Обычный текст: в группе молчим, в личке подсказываем.
        if ($private) {
            $db = db_read();
            say($chat, chat_allowed($db, $chat) ? help_text() : start_hint());
        }
        return;
    }
    $cmd     = strtolower($mm[1]);
    $botName = $mm[2] ?? '';
    $arg     = trim($mm[3] ?? '');

    $db = db_read();
    // В группе команда может быть адресована другому боту: /stats@OtherBot
    if ($botName !== '' && $db['bot_username'] !== '' && strcasecmp($botName, $db['bot_username']) !== 0) {
        return;
    }
    if (preg_match('~^lead_?(\d+)$~', $cmd, $x)) {
        $cmd = 'lead';
        $arg = $x[1];
    }

    if ($cmd === 'start') {
        cmd_start($m, $chat, $private, $arg, $db);
        return;
    }
    if (!chat_allowed($db, $chat)) {
        if ($private) {
            say($chat, start_hint());
        }
        return;
    }

    switch ($cmd) {
        case 'new':
            cmd_list($chat, $db, 'new');
            break;
        case 'work':
            cmd_list($chat, $db, 'work');
            break;
        case 'leads':
            cmd_list($chat, $db, '');
            break;
        case 'lead':
            cmd_lead($chat, (int)$arg);
            break;
        case 'stats':
            cmd_stats($chat, $db);
            break;
        case 'stop':
            cmd_stop($chat);
            break;
        case 'help':
        default:
            say($chat, help_text());
    }
}

function start_hint(): string
{
    return "👋 Это бот заявок DaAuto для сотрудников.\n\n"
        . "Чтобы получать заявки с сайта, отправьте:\n<code>/start код_доступа</code>\n\n"
        . 'Код доступа — у администратора сайта.';
}

function cmd_start(array $m, string $chat, bool $private, string $arg, array $db): void
{
    if (chat_allowed($db, $chat)) {
        say($chat, "✅ Этот чат уже подключён — заявки приходят сюда.\n\n" . help_text());
        return;
    }
    $code = cfg('access_code');
    if ($code === '') {
        say($chat, 'Доступ ещё не настроен: администратору нужно вписать access_code в config.php.');
        return;
    }
    if ($arg === '') {
        say($chat, start_hint());
        return;
    }
    if ($private) {
        // Убираем сообщение с кодом из переписки, чтобы он не остался на экране.
        tg('deleteMessage', ['chat_id' => $chat, 'message_id' => (int)$m['message_id']]);
    }

    $now   = time();
    $title = $private ? user_name($m['from'] ?? []) : (string)($m['chat']['title'] ?? 'группа');
    $by    = user_name($m['from'] ?? []);
    $res   = db_update(static function (array &$db) use ($chat, $arg, $code, $now, $title, $by): string {
        $fails = array_values(array_filter($db['fails'][$chat] ?? [], static function ($t) use ($now) {
            return $t > $now - 3600;
        }));
        if (count($fails) >= 5) {
            $db['fails'][$chat] = $fails;
            return 'locked';
        }
        if (!hash_equals($code, $arg)) {
            $fails[] = $now;
            $db['fails'][$chat] = $fails;
            return 'bad';
        }
        unset($db['fails'][$chat]);
        $db['subs'][$chat] = ['title' => $title, 'by' => $by, 'since' => $now];
        return 'ok';
    });

    if ($res === 'ok') {
        say($chat, "✅ Готово! Новые заявки с сайта будут приходить в этот чат.\n\n" . help_text());
    } elseif ($res === 'locked') {
        say($chat, '⛔ Слишком много неверных попыток. Попробуйте через час.');
    } else {
        say($chat, '❌ Неверный код доступа.');
    }
}

function cmd_stop(string $chat): void
{
    if (cfg('chat_id') !== '' && $chat === cfg('chat_id')) {
        say($chat, 'Этот чат указан в config.php (chat_id) — заявки сюда идут всегда. '
            . 'Чтобы отключить, уберите chat_id из config.php.');
        return;
    }
    db_update(static function (array &$db) use ($chat): void {
        unset($db['subs'][$chat]);
    });
    say($chat, "🔕 Отключено: заявки сюда больше не приходят.\nВключить снова — <code>/start код_доступа</code>");
}

function cmd_list(string $chat, array $db, string $status): void
{
    $leads = array_reverse($db['leads']);
    if ($status !== '') {
        $leads = array_values(array_filter($leads, static function ($l) use ($status) {
            return ($l['status'] ?? 'new') === $status;
        }));
    }
    $total = count($leads);
    $limit = $status === '' ? 10 : 20;
    $titles = [
        ''     => '📋 <b>Последние заявки</b>',
        'new'  => '🆕 <b>Ждут менеджера: ' . $total . '</b>',
        'work' => '🙋 <b>В работе: ' . $total . '</b>',
    ];
    $empty = [
        ''     => 'Заявок пока нет.',
        'new'  => '👍 Новых заявок нет — все разобраны.',
        'work' => 'Сейчас в работе ничего нет.',
    ];
    if ($total === 0) {
        say($chat, $empty[$status]);
        return;
    }
    $lines = [$titles[$status], ''];
    foreach (array_slice($leads, 0, $limit) as $l) {
        $lines[] = lead_brief($l);
    }
    if ($total > $limit) {
        $lines[] = '';
        $lines[] = '…и ещё ' . ($total - $limit) . '. Показаны самые свежие.';
    }
    say($chat, implode("\n", $lines));
}

function cmd_lead(string $chat, int $id): void
{
    $db = db_read();
    $i  = $id > 0 ? lead_index($db, $id) : null;
    if ($i === null) {
        say($chat, 'Заявка №' . $id . ' не найдена.');
        return;
    }
    $r = send_card($chat, $db['leads'][$i]);
    if (!empty($r['ok'])) {
        // Запоминаем и эту копию карточки — её статус тоже будет обновляться.
        $ref = [$chat, (int)$r['result']['message_id']];
        db_update(static function (array &$db) use ($id, $ref): void {
            $i = lead_index($db, $id);
            if ($i !== null) {
                $db['leads'][$i]['msgs'][] = $ref;
                $db['leads'][$i]['msgs'] = array_slice($db['leads'][$i]['msgs'], -20);
            }
        });
    }
}

function cmd_stats(string $chat, array $db): void
{
    $now    = time();
    $today  = strtotime('today');
    $count  = ['today' => 0, 'd7' => 0, 'd30' => 0, 'all' => 0];
    $open   = ['new' => 0, 'work' => 0];
    $closed = ['done' => 0, 'lost' => 0];
    $svc    = [];
    foreach ($db['leads'] as $l) {
        $t  = (int)$l['created'];
        $st = $l['status'] ?? 'new';
        $count['all']++;
        if ($t >= $today) {
            $count['today']++;
        }
        if ($t >= $now - 7 * 86400) {
            $count['d7']++;
        }
        if ($t >= $now - 30 * 86400) {
            $count['d30']++;
            $name = service_name((string)$l['service']);
            $svc[$name] = ($svc[$name] ?? 0) + 1;
        }
        if (isset($open[$st])) {
            $open[$st]++;
        }
        if (isset($closed[$st]) && (int)$l['status_at'] >= $now - 30 * 86400) {
            $closed[$st]++;
        }
    }
    arsort($svc);
    $lines = [
        '📊 <b>Заявки с сайта</b>',
        '',
        'Сегодня: <b>' . $count['today'] . '</b> · 7 дней: <b>' . $count['d7'] . '</b> · 30 дней: <b>' . $count['d30']
            . '</b> · всего: <b>' . $count['all'] . '</b>',
        'Сейчас: 🆕 новых ' . $open['new'] . ' · 🙋 в работе ' . $open['work'],
        'Закрыто за 30 дней: ✅ сделок ' . $closed['done'] . ' · ❌ отказов ' . $closed['lost'],
    ];
    if ($svc) {
        $lines[] = '';
        $lines[] = '<b>Услуги за 30 дней:</b>';
        foreach ($svc as $name => $n) {
            $lines[] = '• ' . h($name) . ' — ' . $n;
        }
    }
    say($chat, implode("\n", $lines));
}

/* =========================================================
   Кнопки под заявкой
   ========================================================= */
function on_callback(array $q): void
{
    $qid  = (string)$q['id'];
    $from = $q['from'] ?? [];
    $msg  = $q['message'] ?? null;
    if (!$msg || !preg_match('~^s:(\d+):(new|work|done|lost)$~', (string)($q['data'] ?? ''), $mm)) {
        tg('answerCallbackQuery', ['callback_query_id' => $qid]);
        return;
    }
    $chat = (string)$msg['chat']['id'];
    $ref  = [$chat, (int)$msg['message_id']];
    $id   = (int)$mm[1];
    $to   = $mm[2];
    $who  = user_name($from);
    $uid  = (int)($from['id'] ?? 0);
    $now  = time();

    $res = db_update(static function (array &$db) use ($chat, $ref, $id, $to, $who, $uid, $now): array {
        if (!chat_allowed($db, $chat)) {
            return ['err' => 'Нет доступа: этот чат не подключён к заявкам.'];
        }
        $i = lead_index($db, $id);
        if ($i === null) {
            return ['err' => 'Заявка не найдена.'];
        }
        $l = &$db['leads'][$i];
        if (!in_array($ref, $l['msgs'] ?? [], true)) {
            $l['msgs'][] = $ref;
        }
        if ($l['status'] === $to) {
            $err = $to === 'work' && (int)$l['by_id'] !== $uid
                ? 'Уже в работе у: ' . $l['by']
                : 'Статус уже такой.';
            return ['err' => $err, 'lead' => $l];
        }
        $l['status']    = $to;
        $l['by']        = $to === 'new' ? '' : $who;
        $l['by_id']     = $to === 'new' ? 0 : $uid;
        $l['status_at'] = $now;
        $l['history'][] = [$now, $to, $who];
        return ['lead' => $l];
    });

    if (isset($res['err'])) {
        tg('answerCallbackQuery', ['callback_query_id' => $qid, 'text' => $res['err'], 'show_alert' => true]);
        if (isset($res['lead'])) {
            refresh_cards($res['lead']); // карточка могла устареть — подтягиваем актуальный статус
        }
        return;
    }
    tg('answerCallbackQuery', ['callback_query_id' => $qid, 'text' => 'Статус: ' . DA_STATUSES[$to][1]]);
    refresh_cards($res['lead']);
}
