@echo off
:: ============================================
::  SETUP (correr 1 vez) - prepara o servidor
::  para receber deploys via git push
::  NAO toca em .env, storage, vendor, uploads
:: ============================================
setlocal
cd /d C:\laragon\www\associacaosantana

set REMOTE=plesk-dev
set WEBROOT=/var/www/vhosts/ardcsantana.ateneya.com/httpdocs

echo [1/3] Inicializar git no servidor...
ssh -o StrictHostKeyChecking=no %REMOTE% "git config --global --add safe.directory %WEBROOT% ; cd %WEBROOT% && if [ ! -d .git ]; then git init . ; fi && git symbolic-ref HEAD refs/heads/main && git config receive.denyCurrentBranch ignore && echo GIT_SERVIDOR_OK"
if %ERRORLEVEL% NEQ 0 ( echo ERRO & pause & exit /b 1 )

echo.
echo [2/3] Configurar remote 'producao' local...
git remote remove producao 2>nul
git remote add producao ssh://%REMOTE%%WEBROOT%
git remote -v

echo.
echo [3/3] Primeiro push + sincronizar codigo...
git push producao main --force
if %ERRORLEVEL% NEQ 0 ( echo ERRO no push & pause & exit /b 1 )
ssh -o StrictHostKeyChecking=no %REMOTE% "cd %WEBROOT% && git checkout -f main 2>/dev/null || git reset --hard main ; git config receive.denyCurrentBranch updateInstead && echo SYNC_OK"
if %ERRORLEVEL% NEQ 0 ( echo ERRO no sync & pause & exit /b 1 )

echo.
echo SETUP CONCLUIDO. A partir de agora usa deploy.bat
pause
endlocal
