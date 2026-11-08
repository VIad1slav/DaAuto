@echo off
rem DaAuto - local preview. Double-click to open the site in your browser.
rem Close this window to stop the site. (Telegram sending needs PHP hosting, it does not work here.)
cd /d "%~dp0"
start "" "http://localhost:8080/?lang=ru"
python -m http.server 8080
pause
