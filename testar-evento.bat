@echo off
:: ============================================================
::  Ensaio do evento: corre os testes do pre-pagamento (3 postos,
::  senhas, impressao, caucoes, caixa, agente do Raspberry).
::  Usa uma base de dados SO de testes (santana_test), que e apagada
::  e recriada - nunca toca na base de dados do site.
:: ============================================================
cd /d C:\laragon\www\associacaosantana

if not exist vendor\autoload.php (
    echo A instalar dependencias PHP...
    call composer install
)

echo A preparar a base de dados de testes (santana_test)...
mysql -uroot -e "CREATE DATABASE IF NOT EXISTS santana_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
if errorlevel 1 (
    echo ERRO: nao consegui falar com o MySQL do Laragon. Carrega em "Start All" no Laragon e tenta outra vez.
    pause & exit /b 1
)

php vendor\bin\phpunit -c phpunit.evento.xml --testdox
if errorlevel 1 (
    echo.
    echo ==========  HA TESTES A FALHAR - NAO FAZER DEPLOY  ==========
    pause & exit /b 1
)

echo.
echo ==========  TUDO OK - pode fazer deploy  ==========
pause
