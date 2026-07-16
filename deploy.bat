@echo off
:: ============================================
::  DEPLOY - ardcsantana.ateneya.com
::  Requer setup-servidor.bat executado 1 vez
:: ============================================
setlocal
cd /d C:\laragon\www\associacaosantana

set REMOTE=plesk-dev
set WEBROOT=/var/www/vhosts/ardcsantana.ateneya.com/httpdocs
set PHP=/opt/plesk/php/8.3/bin/php

:: Avisar se ha alteracoes por commitar
git diff --quiet & git diff --cached --quiet
if %ERRORLEVEL% NEQ 0 (
    echo AVISO: tens alteracoes por commitar. Corre push.bat primeiro
    echo se quiseres que essas alteracoes vao para o servidor.
    pause
)

echo.
echo [1/4] Build assets (npm)...
call npm run build
if %ERRORLEVEL% NEQ 0 ( echo ERRO no build & pause & exit /b 1 )

echo.
echo [2/4] Enviar codigo para o servidor (git push)...
git push producao main
if %ERRORLEVEL% NEQ 0 ( echo ERRO no push para o servidor & pause & exit /b 1 )

echo.
echo [3/4] Enviar assets compilados...
scp -r -o StrictHostKeyChecking=no public\build %REMOTE%:%WEBROOT%/public/
if %ERRORLEVEL% NEQ 0 ( echo ERRO no scp & pause & exit /b 1 )
if exist bootstrap\ssr scp -r -o StrictHostKeyChecking=no bootstrap\ssr %REMOTE%:%WEBROOT%/bootstrap/

echo.
echo [4/4] Migracoes + cache no servidor...
ssh -o StrictHostKeyChecking=no %REMOTE% "cd %WEBROOT% && %PHP% artisan migrate --force && %PHP% artisan optimize:clear && echo DEPLOY_OK"
if %ERRORLEVEL% NEQ 0 ( echo ERRO nas migracoes/cache & pause & exit /b 1 )

echo.
echo =============================================
echo   DEPLOY CONCLUIDO - ardcsantana.ateneya.com
echo =============================================
pause
endlocal
