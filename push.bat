@echo off
:: ============================================
::  PUSH - commit + push para GitHub
::  Uso: push.bat mensagem do commit
:: ============================================
cd /d C:\laragon\www\associacaosantana
if exist .git\index.lock del .git\index.lock

git add -A

set "MSG=%*"
if "%MSG%"=="" (
    for /f "tokens=1-3 delims=/ " %%a in ("%date%") do set "HOJE=%%a-%%b-%%c"
    set "MSG=update: alteracoes"
)

git commit -m "%MSG%"
if %ERRORLEVEL% NEQ 0 (
    echo Nada para commitar ou erro no commit.
)

echo.
echo === Push para GitHub ===
git push origin main
if %ERRORLEVEL% NEQ 0 ( echo ERRO no push & pause & exit /b 1 )
echo PUSH OK
pause
