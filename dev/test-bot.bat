@echo off
rem DaAuto - test the Telegram bot on this computer, without hosting.
rem Double-click. Needs daauto-site\config.php filled in. Close this window to stop.
chcp 65001 >nul
cd /d "%~dp0"
set "PHPEXE=%~dp0php\php.exe"
if not exist "%PHPEXE%" (
  echo PHP not found: %PHPEXE%
  pause
  exit /b 1
)
python -I "%~dp0make-cacert.py" "%~dp0php\cacert.pem" 2>nul
if errorlevel 1 if not exist "%~dp0php\cacert.pem" (
  echo Could not prepare certificates: Python is needed for the first start.
  pause
  exit /b 1
)
set "DAAUTO_DATA_DIR=%~dp0data"
rem No "5 leads per 10 minutes" limit while testing
set "DAAUTO_NO_RATE_LIMIT=1"
"%PHPEXE%" -d "extension_dir=%~dp0php\ext" -d "curl.cainfo=%~dp0php\cacert.pem" -d "openssl.cafile=%~dp0php\cacert.pem" "%~dp0poll.php"
pause
