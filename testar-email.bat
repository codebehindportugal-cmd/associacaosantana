@echo off
:: Diagnostico completo de email em producao
setlocal
set REMOTE=plesk-dev
set WEBROOT=/var/www/vhosts/ardcsantana.ateneya.com/httpdocs
set PHP=/opt/plesk/php/8.3/bin/php

echo === [1] Config de mail no .env (password escondida) ===
ssh -o StrictHostKeyChecking=no %REMOTE% "cd %WEBROOT% && grep -E '^MAIL|^CONTACT' .env | sed 's/^MAIL_PASSWORD=.*/MAIL_PASSWORD=********/'"

echo.
echo === [2] Postfix a ouvir nas portas 25/587? ===
ssh -o StrictHostKeyChecking=no %REMOTE% "ss -ltn 2>/dev/null | grep -E ':25 |:587 ' || netstat -ltn 2>/dev/null | grep -E ':25 |:587 ' || echo NADA A OUVIR em 25/587"

echo.
echo === [3] Limpar cache e testar envio ===
ssh -o StrictHostKeyChecking=no %REMOTE% "cd %WEBROOT% && %PHP% artisan config:clear >/dev/null && %PHP% artisan email:teste ardcsantana@outlook.com"

echo.
echo === [4] Ultimo erro de mail no log ===
ssh -o StrictHostKeyChecking=no %REMOTE% "cd %WEBROOT% && grep -iE 'connection|authenticat|mail|smtp' storage/logs/laravel.log 2>/dev/null | tail -5"

echo.
echo === [5] Fila do Postfix (emails presos?) ===
ssh -o StrictHostKeyChecking=no %REMOTE% "mailq 2>/dev/null | tail -8 || echo sem mailq"

pause
endlocal
