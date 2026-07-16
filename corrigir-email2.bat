@echo off
:: Corrige MAIL_HOST para o hostname real (o certificado TLS e desse nome)
setlocal
set REMOTE=plesk-dev
set WEBROOT=/var/www/vhosts/ardcsantana.ateneya.com/httpdocs
set PHP=/opt/plesk/php/8.3/bin/php

echo [1/2] Trocar MAIL_HOST=localhost pelo hostname real...
ssh -o StrictHostKeyChecking=no %REMOTE% "cd %WEBROOT% && sed -i 's/^MAIL_HOST=.*/MAIL_HOST=vmi2463138.contaboserver.net/' .env && grep '^MAIL_HOST' .env"

echo.
echo [2/2] Limpar cache e testar...
ssh -o StrictHostKeyChecking=no %REMOTE% "cd %WEBROOT% && %PHP% artisan config:clear >/dev/null && %PHP% artisan email:teste ardcsantana@outlook.com"

echo.
echo Se der EMAIL ENVIADO OK, ve a caixa de entrada E o spam.
pause
endlocal
