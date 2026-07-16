@echo off
:: ============================================
::  DEPLOY - ardcsantana.ateneya.com
::  Uso: deploy.bat        (completo)
::       deploy.bat php    (so codigo PHP, sem build/assets)
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

if /i "%~1"=="php" goto :pushcode

echo.
echo [1/3] Build assets (npm)...
call npm run build
if %ERRORLEVEL% NEQ 0 ( echo ERRO no build & pause & exit /b 1 )

:pushcode
echo.
echo [2/3] Enviar codigo (git push)...
git push producao main
if %ERRORLEVEL% NEQ 0 ( echo ERRO no push para o servidor & pause & exit /b 1 )

echo.
echo [3/3] Assets + migracoes + cache (1 ligacao)...
if /i "%~1"=="php" (
    ssh -o StrictHostKeyChecking=no %REMOTE% "cd %WEBROOT% && [ -L public/storage ] || %PHP% artisan storage:link ; %PHP% artisan migrate --force && %PHP% artisan db:seed --class=RoleSeeder --force && %PHP% artisan optimize:clear && echo DEPLOY_OK"
) else (
    :: Envia build + ssr (substituidos) e storage/app/public (adicionado, sem apagar uploads do servidor)
    tar -czf - public/build bootstrap/ssr storage/app/public | ssh -o StrictHostKeyChecking=no %REMOTE% "cd %WEBROOT% && rm -rf public/build bootstrap/ssr && tar -xzf - && ([ -L public/storage ] || %PHP% artisan storage:link) ; %PHP% artisan migrate --force && %PHP% artisan db:seed --class=RoleSeeder --force && %PHP% artisan optimize:clear && echo DEPLOY_OK"
)
if %ERRORLEVEL% NEQ 0 ( echo ERRO no passo final & pause & exit /b 1 )

echo.
echo =============================================
echo   DEPLOY CONCLUIDO - ardcsantana.ateneya.com
echo =============================================
pause
endlocal
