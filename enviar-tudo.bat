@echo off
:: ============================================================
::  ENVIAR TUDO - de uma vez, para o GitHub e para o servidor
::  (ardcsantana.ateneya.com)
::
::  Uso:  enviar-tudo.bat                    (mensagem automatica)
::        enviar-tudo.bat a mensagem do commit
::
::  Faz por esta ordem, e para no primeiro erro:
::    1. Compila o site (npm run build)
::    2. Grava tudo num commit
::    3. Envia para o GitHub (se falhar, avisa e continua)
::    4. Envia o codigo para o servidor
::    5. No servidor: ficheiros compilados, migracoes, permissoes e cache
:: ============================================================
setlocal
cd /d C:\laragon\www\associacaosantana

set REMOTE=plesk-dev
set WEBROOT=/var/www/vhosts/ardcsantana.ateneya.com/httpdocs
set PHP=/opt/plesk/php/8.3/bin/php

if exist .git\index.lock del .git\index.lock

set "MSG=%*"
if "%MSG%"=="" set "MSG=update: alteracoes"

echo.
echo [1/5] A compilar o site (npm run build)...
call npm run build
if %ERRORLEVEL% NEQ 0 ( echo. & echo ERRO na compilacao. Nada foi enviado. & pause & exit /b 1 )

echo.
echo [2/5] A gravar as alteracoes (commit)...
git add -A
git diff --cached --quiet
if %ERRORLEVEL% NEQ 0 (
    git commit -m "%MSG%"
    if errorlevel 1 ( echo. & echo ERRO no commit. Nada foi enviado. & pause & exit /b 1 )
) else (
    echo Nada de novo para gravar - segue com o que ja esta gravado.
)

echo.
echo [3/5] A enviar para o GitHub...
git push origin main
if %ERRORLEVEL% NEQ 0 (
    echo.
    echo AVISO: o envio para o GitHub falhou. O servidor vai ser atualizado na mesma;
    echo        volta a correr "git push origin main" mais tarde.
    set "GITHUB_FALHOU=1"
)

echo.
echo [4/5] A enviar o codigo para o servidor...
git push producao main
if %ERRORLEVEL% NEQ 0 ( echo. & echo ERRO a enviar para o servidor. O site NAO foi atualizado. & pause & exit /b 1 )

echo.
echo [5/5] Servidor: ficheiros compilados + migracoes + cache...
:: public/build e bootstrap/ssr sao substituidos; storage/app/public e so acrescentado (nao apaga uploads)
tar -czf - public/build bootstrap/ssr storage/app/public | ssh -o StrictHostKeyChecking=no %REMOTE% "cd %WEBROOT% && rm -rf public/build bootstrap/ssr && tar -xzf - && ([ -L public/storage ] || %PHP% artisan storage:link) ; %PHP% artisan migrate --force && %PHP% artisan db:seed --class=RoleSeeder --force && %PHP% artisan optimize:clear && echo DEPLOY_OK"
if %ERRORLEVEL% NEQ 0 ( echo. & echo ERRO no servidor - o codigo foi enviado mas as migracoes/cache falharam. Avisa o Claude. & pause & exit /b 1 )

echo.
echo ============================================================
echo   TUDO ENVIADO - ardcsantana.ateneya.com
if defined GITHUB_FALHOU echo   (falta so o GitHub: corre "git push origin main" mais tarde)
echo.
echo   Se mudou o agente das impressoras (local-printer-agent\agent.mjs),
echo   atualiza o Raspberry: copia o agent.mjs e corre o setup-pi.sh.
echo ============================================================
pause
endlocal
