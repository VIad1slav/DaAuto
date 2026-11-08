@echo off
rem DaAuto - local preview. Double-click to open the site in your browser.
rem Close this window to stop the site. (Telegram sending needs PHP: use dev\test-bot.bat.)
rem Latin only in this file: Cyrillic breaks cmd parsing.
cd /d "%~dp0"
set "URL=http://localhost:8080/?lang=ru"

rem Already running (second double-click) - just open the browser.
netstat -ano | findstr /r /c:":8080 .*LISTENING" >nul && (
  start "" "%URL%"
  exit /b 0
)

where python >nul 2>&1 || (
  echo Python not found. Install it from python.org and run this file again.
  pause
  exit /b 1
)

echo DaAuto site: %URL%
echo.
echo Keep this window open while you view the site. Close it to stop the site.
echo You can refresh the page as often as you like.

rem Browser opens after the server is up, not before.
start "" /b cmd /c "timeout /t 2 /nobreak >nul & start http://localhost:8080/?lang=ru"

rem Server output goes to a file, not this window: a click inside the window
rem (selection mode) would otherwise freeze the server and the page would stop loading.
rem --bind 127.0.0.1: only this computer can open it (this server would hand out config.php as text).
python -m http.server 8080 --bind 127.0.0.1 >"%TEMP%\daauto-site.log" 2>&1

echo.
echo Site stopped. Details: %TEMP%\daauto-site.log
pause
