"""DaAuto — временная публичная ссылка на сайт с этого компьютера (Cloudflare Tunnel).

Запускается из dev\\share-site.bat (двойной щелчок). Ссылку вида
https://....trycloudflare.com можно открыть с любого телефона; заявки
с неё приходят в Telegram-бот так же, как с localhost.

Работает, пока открыто это окно и окно test-bot.bat. Каждый запуск —
новая ссылка. На хостинг эта папка не нужна.
"""
import os
import re
import socket
import subprocess
import sys
import time
import urllib.error
import urllib.request

DEV = os.path.dirname(os.path.abspath(__file__))
PORT = int(os.environ.get('DAAUTO_PORT') or 8090)  # как в poll.php
LOCAL = f'http://127.0.0.1:{PORT}'
CLOUDFLARED = os.path.join(DEV, 'cloudflared.exe')
LOG = os.path.join(DEV, 'cloudflared.log')
TEST_BOT = os.path.join(DEV, 'test-bot.bat')

if not sys.stdout.isatty():
    sys.stdout.reconfigure(encoding='utf-8')


def say(s=''):
    print(s, flush=True)


def stop(msg):
    say()
    say('❌ ' + msg)
    sys.exit(1)


def port_open():
    try:
        with socket.create_connection(('127.0.0.1', PORT), timeout=0.5):
            return True
    except OSError:
        return False


def status(url, timeout=5):
    """HTTP-код ответа или None, если адрес не отвечает."""
    try:
        with urllib.request.urlopen(url, timeout=timeout) as r:
            return r.status
    except urllib.error.HTTPError as e:
        return e.code
    except (urllib.error.URLError, OSError):
        return None


say('DaAuto — ссылка на сайт для телефона')
say('─' * 52)

if not os.path.isfile(CLOUDFLARED):
    stop('Нет программы dev\\cloudflared.exe.\n'
         '   Скачайте cloudflared-windows-amd64.exe с github.com/cloudflare/cloudflared/releases\n'
         '   и сохраните как dev\\cloudflared.exe.')

# 1. Сайт с ботом должен работать — иначе ссылке нечего показывать.
if not port_open():
    say('Сайт с ботом не запущен — открываю test-bot.bat в отдельном окне…')
    say('(если там появится вопрос — ответьте в том окне)')
    subprocess.Popen(f'start "DaAuto bot" "{TEST_BOT}"', shell=True, cwd=DEV)
    for _ in range(180):
        if port_open():
            break
        time.sleep(0.5)
    else:
        stop('Сайт так и не запустился. Посмотрите окно test-bot.bat.')
    time.sleep(1)

# 2. Наружу открываем только сайт, который не отдаёт служебные файлы.
#    Старый test-bot.bat (до 08.11.2026) отдавал config.php с токеном по адресу config.php::$DATA.
for path in ('/config.php::$DATA', '/config.php', '/README.md', '/lib/core.php'):
    if status(LOCAL + urllib.request.quote(path)) != 404:
        stop('Окно test-bot.bat запущено старой версией — она может отдать наружу config.php с токеном.\n'
             '   Закройте окно test-bot.bat и запустите share-site.bat снова.')

# 3. Туннель.
say('Открываю туннель Cloudflare…')
log = open(LOG, 'w', encoding='utf-8', errors='replace')
proc = subprocess.Popen([CLOUDFLARED, 'tunnel', '--no-autoupdate', '--url', LOCAL],
                        stdout=log, stderr=subprocess.STDOUT, cwd=DEV)
try:
    url = None
    for _ in range(120):
        time.sleep(0.5)
        if proc.poll() is not None:
            break
        with open(LOG, encoding='utf-8', errors='replace') as f:
            m = re.search(r'https://[a-z0-9-]+\.trycloudflare\.com', f.read())
        if m:
            url = m.group(0)
            break
    if not url:
        stop('Туннель не открылся. Проверьте интернет; подробности — dev\\cloudflared.log')

    # Новому адресу нужно несколько секунд, чтобы заработать.
    for _ in range(20):
        if status(url + '/', timeout=10) == 200:
            break
        time.sleep(2)

    link_ru = url + '/?lang=ru'
    link_pl = url + '/?lang=pl'
    try:
        subprocess.run('clip', input=link_ru.encode('ascii'), check=False)
        copied = '  (уже скопирована — вставьте в мессенджер)'
    except OSError:
        copied = ''

    say()
    say('✅ Сайт открыт по ссылке' + copied + ':')
    say()
    say('   ' + link_ru)
    say('   польская версия: ' + link_pl)
    say()
    say('Ссылка работает, пока открыты это окно и окно test-bot.bat.')
    say('Заявки с неё приходят в Telegram-бот. Каждый запуск — новая ссылка.')
    say('Закрыть доступ — закройте это окно.')
    say('─' * 52)

    site_down = False
    while proc.poll() is None:
        time.sleep(5)
        up = port_open()
        if not up and not site_down:
            say(time.strftime('%H:%M:%S') + '  ⚠️  окно test-bot.bat закрыто — по ссылке сайт не откроется. Запустите его снова.')
        elif up and site_down:
            say(time.strftime('%H:%M:%S') + '  сайт снова работает, ссылка та же')
        site_down = not up
    stop('Туннель закрылся. Запустите share-site.bat снова — будет новая ссылка.')
except KeyboardInterrupt:
    pass
finally:
    if proc.poll() is None:
        proc.terminate()
    log.close()
