@echo off
:: ============================================
::  LIMPAR SERVIDOR - remove ficheiros que nao
::  pertencem ao projeto (untracked no git)
::  Requer setup-servidor.bat executado antes
:: ============================================
setlocal
set REMOTE=plesk-dev
set WEBROOT=/var/www/vhosts/ardcsantana.ateneya.com/httpdocs

:: Pastas/ficheiros a PRESERVAR (nao tocar):
set EXCL=-e .env -e storage -e vendor -e node_modules -e public/build -e public/hot -e bootstrap/ssr -e public/images/events/uploads -e .well-known -e error_docs -e cgi-bin

echo === Ficheiros que seriam APAGADOS no servidor (simulacao) ===
ssh -o StrictHostKeyChecking=no %REMOTE% "cd %WEBROOT% && git clean -nd %EXCL%"
echo.
echo =============================================
echo Revê a lista acima. Se estiver correta,
set /p CONF="escreve SIM para apagar: "
if /i not "%CONF%"=="SIM" ( echo Cancelado. & pause & exit /b 0 )

ssh -o StrictHostKeyChecking=no %REMOTE% "cd %WEBROOT% && git clean -fd %EXCL% && echo LIMPEZA_OK"
pause
endlocal
