@echo off
:: Corre composer install no servidor (necessario apos adicionar nova dependencia)
cd /d C:\laragon\www\associacaosantana

set REMOTE=plesk-dev
set WEBROOT=/var/www/vhosts/ardcsantana.ateneya.com/httpdocs
set PHP=/opt/plesk/php/8.3/bin/php

echo === COMPOSER INSTALL NO SERVIDOR ===
echo.

ssh -o StrictHostKeyChecking=no %REMOTE% "cd %WEBROOT% && %PHP% /usr/local/bin/composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -15 && echo COMPOSER_OK"
if %ERRORLEVEL% NEQ 0 (
    echo Composer nao encontrado em /usr/local/bin — a tentar alternativa...
    ssh -o StrictHostKeyChecking=no %REMOTE% "cd %WEBROOT% && composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | tail -15 && echo COMPOSER_OK"
)

echo.
echo === Concluido ===
pause
