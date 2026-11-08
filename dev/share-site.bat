@echo off
rem DaAuto - temporary public link to the site on this computer (to open it on a phone).
rem Double-click. Starts test-bot.bat if needed, then a Cloudflare tunnel. Close this window to stop.
rem Latin only in this file: Cyrillic breaks cmd parsing. Russian text is printed by share.py.
chcp 65001 >nul
cd /d "%~dp0"
where python >nul 2>&1 || (
  echo Python not found. Install it from python.org and run this file again.
  pause
  exit /b 1
)
python -I "%~dp0share.py"
pause
