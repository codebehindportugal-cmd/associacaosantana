@echo off
:: ============================================
::  SETUP (correr 1 vez) - prepara o servidor
::  para receber deploys via git push
:: ============================================
setlocal
cd /d C:\laragon\www\associacaosantana

set REMOTE=plesk-dev
set WEBROOT=/var/www/vhosts/ardcsantana.ateneya.com/httpdocs

echo [1/3] Inicializar git no servidor...
ssh -o StrictHostKeyChecking=no %REMOTE% "cd %WEBROOT% && if [ ! -d .git ]; then git init -b main .; fi && git config receive.denyCurrentBranch updateInstead && echo GIT_SERVIDOR_OK"
if %ERRORLEVEL% NEQ 0 ( echo ERRO & pause & exit /b 1 )

echo.
echo [2/3] Configurar remote 'producao' local...
git remote remove producao 2>nul
git remote add producao ssh://%REMOTE%%WEBROOT%
git remote -v

echo.
echo [3/3] Primeiro push + sincronizar working tree...
git push producao main --force
ssh -o StrictHostKeyChecking=no %REMOTE% "cd %WEBROOT% && git checkout -f main 2>/dev/null || git reset --hard main && echo SYNC_OK"

echo.
echo SETUP CONCLUIDO. A partir de agora usa deploy.bat
pause
endlocal
