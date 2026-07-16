@echo off
:: Corrige a configuracao de email no .env do servidor (faz backup primeiro)
setlocal
set REMOTE=plesk-dev
set WEBROOT=/var/www/vhosts/ardcsantana.ateneya.com/httpdocs
set PHP=/opt/plesk/php/8.3/bin/php

echo [1/4] Backup do .env...
ssh -o StrictHostKeyChecking=no %REMOTE% "cd %WEBROOT% && cp .env .env.backup-$(date +%%Y%%m%%d%%H%%M%%S) && echo BACKUP_OK"
if %ERRORLEVEL% NEQ 0 ( echo ERRO no backup & pause & exit /b 1 )

echo.
echo [2/4] Remover linhas MAIL antigas e escrever bloco limpo...
ssh -o StrictHostKeyChecking=no %REMOTE% "cd %WEBROOT% && sed -i '/^MAIL_/d;/^CONTACT_MAIL_TO/d' .env && printf '\nMAIL_MAILER=smtp\nMAIL_SCHEME=null\nMAIL_HOST=localhost\nMAIL_PORT=25\nMAIL_USERNAME=null\nMAIL_PASSWORD=null\nMAIL_FROM_ADDRESS=geral@ardcsantana.ateneya.com\nMAIL_FROM_NAME=\"ARDC Santana\"\nCONTACT_MAIL_TO=ardcsantana@outlook.com\nMAIL_REPLY_TO_ADDRESS=ardcsantana@outlook.com\n' >> .env && grep -E '^MAIL|^CONTACT' .env"

echo.
echo [3/4] Limpar cache de config...
ssh -o StrictHostKeyChecking=no %REMOTE% "cd %WEBROOT% && %PHP% artisan config:clear"

echo.
echo [4/4] Enviar email de teste...
ssh -o StrictHostKeyChecking=no %REMOTE% "cd %WEBROOT% && %PHP% artisan tinker --execute=\"try { Mail::raw('Teste SMTP do site - '.now(), function($m) { $m->to('ardcsantana@outlook.com')->subject('TESTE SMTP ARDC'); }); echo PHP_EOL.'EMAIL_ENVIADO_OK'.PHP_EOL; } catch (\Throwable $e) { echo PHP_EOL.'ERRO: '.$e->getMessage().PHP_EOL; }\""

echo.
echo Ve a caixa (e o SPAM) de ardcsantana@outlook.com
pause
endlocal
