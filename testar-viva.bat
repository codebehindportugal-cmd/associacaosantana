@echo off
:: Diagnostico da integracao Viva Payments em producao
setlocal
set REMOTE=plesk-dev
set WEBROOT=/var/www/vhosts/ardcsantana.ateneya.com/httpdocs
set PHP=/opt/plesk/php/8.3/bin/php

echo === [1] Variaveis VIVA no .env do servidor ===
ssh -o StrictHostKeyChecking=no %REMOTE% "cd %WEBROOT% && grep '^VIVA' .env | sed 's/^VIVA_CLIENT_SECRET=.*/VIVA_CLIENT_SECRET=********/' || echo SEM VARIAVEIS VIVA NO .ENV"

echo.
echo === [2] Teste completo (token + ordem) ===
ssh -o StrictHostKeyChecking=no %REMOTE% "cd %WEBROOT% && %PHP% artisan config:clear >/dev/null && %PHP% artisan viva:teste"

echo.
echo === [3] Erros Viva no log ===
ssh -o StrictHostKeyChecking=no %REMOTE% "cd %WEBROOT% && grep -i 'viva' storage/logs/laravel.log 2>/dev/null | tail -5"

pause
endlocal
