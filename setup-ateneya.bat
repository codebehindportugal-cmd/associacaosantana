@echo off
echo ========================================
echo   Ateneya - Setup do Projeto
echo ========================================
echo.

cd /d C:\laragon\www

echo [1/6] A criar projeto Laravel...
composer create-project laravel/laravel app-ateneya --prefer-dist
cd app-ateneya

echo.
echo [2/6] A instalar Laravel Breeze (Inertia + Vue 3)...
composer require laravel/breeze --dev
php artisan breeze:install vue --ssr=false --pest=false

echo.
echo [3/6] A instalar stancl/tenancy (multi-tenancy)...
composer require stancl/tenancy

echo.
echo [4/6] A instalar dependencias npm...
npm install

echo.
echo [5/6] A criar base de dados app_ateneya...
mysql -u root -e "CREATE DATABASE IF NOT EXISTS app_ateneya CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

echo.
echo [6/6] A configurar .env...
powershell -Command "(Get-Content .env) -replace 'DB_DATABASE=laravel', 'DB_DATABASE=app_ateneya' | Set-Content .env"
powershell -Command "(Get-Content .env) -replace 'APP_NAME=Laravel', 'APP_NAME=Ateneya' | Set-Content .env"
powershell -Command "(Get-Content .env) -replace 'APP_URL=http://localhost', 'APP_URL=http://app-ateneya.test' | Set-Content .env"

echo.
echo [7/7] A gerar key e correr migrations...
php artisan key:generate
php artisan migrate

echo.
echo ========================================
echo   Setup concluido!
echo   Abre: http://app-ateneya.test
echo ========================================
pause
