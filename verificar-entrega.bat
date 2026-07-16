@echo off
:: Envia teste e mostra o que o Postfix fez com o email (aceite? recusado? porque?)
setlocal
set REMOTE=plesk-dev
set WEBROOT=/var/www/vhosts/ardcsantana.ateneya.com/httpdocs
set PHP=/opt/plesk/php/8.3/bin/php

echo === [1] Enviar teste do Laravel ===
ssh -o StrictHostKeyChecking=no %REMOTE% "cd %WEBROOT% && %PHP% artisan config:clear >/dev/null && %PHP% artisan email:teste ardcsantana@outlook.com"

echo.
echo === [2] Aguardar 8s pela entrega... ===
timeout /t 8 /nobreak >nul

echo === [3] O que o Postfix fez (ultimas entregas para outlook) ===
ssh -o StrictHostKeyChecking=no %REMOTE% "grep -iE 'outlook|status=' /var/log/maillog 2>/dev/null | tail -15 || journalctl -u postfix --since '5 minutes ago' 2>/dev/null | tail -15 || tail -20 /var/log/mail.log 2>/dev/null"

echo.
echo LEGENDA: status=sent = a Microsoft ACEITOU (ve spam/lixo).
echo status=bounced ou deferred = recusado; a razao aparece na mesma linha.
pause
endlocal
