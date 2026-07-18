@echo off
cd /d C:\laragon\www\associacaosantana

echo === SETUP WEB PUSH NOTIFICATIONS ===
echo.

echo [1] Instalar biblioteca minishlink/web-push...
composer require minishlink/web-push --no-interaction
if %ERRORLEVEL% NEQ 0 ( echo ERRO no composer & pause & exit /b 1 )

echo.
echo [2] Gerar VAPID keys...
php -r "
require 'vendor/autoload.php';
\$keys = \Minishlink\WebPush\VAPID::createVapidKeys();
echo 'VAPID_PUBLIC_KEY=' . \$keys['publicKey'] . PHP_EOL;
echo 'VAPID_PRIVATE_KEY=' . \$keys['privateKey'] . PHP_EOL;
" > vapid_keys.txt

if %ERRORLEVEL% NEQ 0 ( echo ERRO ao gerar keys & pause & exit /b 1 )

echo.
echo === KEYS GERADAS (copiar para .env) ===
type vapid_keys.txt
echo.

echo [3] Adicionar ao .env local automaticamente...
for /f "tokens=1,2 delims==" %%a in (vapid_keys.txt) do (
    findstr /c:"%%a" .env >nul 2>&1
    if errorlevel 1 (
        echo %%a=%%b >> .env
        echo Adicionado: %%a
    ) else (
        echo Ja existe no .env: %%a
    )
)

del vapid_keys.txt

echo.
echo [4] Correr migracao...
php artisan migrate --force

echo.
echo [5] Limpar cache de config...
php artisan config:clear
php artisan optimize:clear

echo.
echo === SETUP CONCLUIDO ===
echo Agora faz push.bat + deploy.bat para enviar para o servidor.
echo No servidor, o .env precisa tambem das VAPID keys.
echo (ver ficheiro vapid_keys.txt que foi gerado antes de ser apagado — ou repetir o passo 2)
echo.
pause
