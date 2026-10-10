@echo off
:: ======================================================================
::  FAZER TUDO - Carvalhal Fest (ardcsantana.ateneya.com)
::
::  1. Poe o projeto em C:\laragon\www\associacaosantana (copia do GitHub)
::  2. Aplica o commit com as alteracoes (santana-alteracoes.patch)
::  3. Envia para o GitHub
::  4. Instala dependencias (composer + npm)
::  5. Corre os testes do evento (se o MySQL do Laragon estiver ligado)
::  6. Faz o deploy para o servidor (deploy.bat: build, codigo, migracoes)
::  7. Opcional: atualiza o agente no Raspberry
::
::  Poe este ficheiro e o santana-alteracoes.patch na MESMA pasta
::  (ex.: Transferencias) e faz duplo clique.
:: ======================================================================
setlocal
set "PASTA=C:\laragon\www\associacaosantana"
set "REPO=https://github.com/codebehindportugal-cmd/associacaosantana.git"
set "PATCH=%~dp0santana-alteracoes.patch"
set "MARCA=preparacao do Carvalhal Fest"
set "BASE=43cd761c72773c35bfd213e25189ea0def611d9b"

echo.
echo ================================================
echo   FAZER TUDO - ardcsantana.ateneya.com
echo ================================================
echo.

if not exist "%PATCH%" (
    echo ERRO: nao encontro o santana-alteracoes.patch ao lado deste ficheiro.
    echo Poe os dois ficheiros na mesma pasta e tenta outra vez.
    goto :erro
)

where git >nul 2>&1 || ( echo ERRO: o git nao esta instalado ou nao esta no PATH. & goto :erro )

:: ---------------------------------------------------------------- 1
echo [1/7] Projeto em %PASTA%
if exist "%PASTA%\.git" goto :temprojeto
if exist "%PASTA%" (
    dir /b "%PASTA%" | findstr . >nul && (
        echo ERRO: %PASTA% existe mas nao e um repositorio git.
        echo Muda-lhe o nome ^(ou apaga-a^) e corre isto outra vez.
        goto :erro
    )
    rmdir "%PASTA%"
)
echo       A copiar do GitHub...
git clone "%REPO%" "%PASTA%" || ( echo ERRO no git clone & goto :erro )
:temprojeto
cd /d "%PASTA%"
if exist .git\index.lock del .git\index.lock

git checkout main || ( echo ERRO ao mudar para main & goto :erro )
git pull --ff-only origin main || ( echo ERRO no git pull - ha alteracoes locais por resolver & goto :erro )

:: ---------------------------------------------------------------- 2
echo.
echo [2/7] Commit das alteracoes
git log --oneline -60 | findstr /c:"%MARCA%" /c:"token do agente no backoffice" >nul
if not errorlevel 1 goto :atualizar
git am --keep-cr "%PATCH%"
if errorlevel 1 (
    echo ERRO ao aplicar o patch. A desfazer...
    git am --abort
    goto :erro
)
goto :aplicado

:atualizar
:: Ja tinha uma versao anterior destas alteracoes: poe os ficheiros delas na
:: versao final (sem mexer noutros ficheiros) e faz um commit de atualizacao.
echo       Ja tinha uma versao anterior - a atualizar para a versao final...
git branch -D carvalhal-final >nul 2>&1
git checkout -q -b carvalhal-final %BASE% || ( echo ERRO: nao encontro o commit base %BASE% & goto :erro )
git am --keep-cr "%PATCH%"
if errorlevel 1 (
    git am --abort
    git checkout -q main
    echo ERRO ao preparar a versao final.
    goto :erro
)
git checkout -q main || goto :erro
for /f "delims=" %%F in ('git diff --name-only %BASE% carvalhal-final') do git checkout carvalhal-final -- "%%F"
git branch -D carvalhal-final >nul 2>&1
git diff --cached --quiet
if not errorlevel 1 (
    echo       Ja estava na versao final - nada a mudar.
    goto :aplicado
)
git commit -q -m "POS pre-pagamento: atualizacao para a versao final (Carvalhal Fest)" || ( echo ERRO no commit de atualizacao & goto :erro )
echo       Atualizado.
:aplicado

:: ---------------------------------------------------------------- 3
echo.
echo [3/7] Enviar para o GitHub
git push origin main || ( echo ERRO no push para o GitHub & goto :erro )

:: ---------------------------------------------------------------- 4
echo.
echo [4/7] Dependencias
call composer install --no-interaction || ( echo ERRO no composer install & goto :erro )
call npm ci --no-audit --no-fund
if errorlevel 1 (
    echo       npm ci falhou, a tentar npm install...
    call npm install --no-audit --no-fund || ( echo ERRO no npm install & goto :erro )
    git checkout -- package-lock.json >nul 2>&1
)

:: ---------------------------------------------------------------- 5
echo.
echo [5/7] Testes do evento
where mysql >nul 2>&1 || goto :semtestes
mysql -uroot -e "CREATE DATABASE IF NOT EXISTS santana_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" >nul 2>&1 || goto :semtestes
php vendor\bin\phpunit -c phpunit.evento.xml --testdox
if errorlevel 1 (
    echo.
    echo ==========  HA TESTES A FALHAR - O DEPLOY NAO FOI FEITO  ==========
    goto :erro
)
echo       Testes OK.
goto :testesfeitos
:semtestes
echo       AVISO: o MySQL do Laragon nao esta ligado - nao deu para correr os testes.
echo       (Para os correr: Laragon ^> Start All, e volta a correr este ficheiro.)
choice /c SN /m "      Continuar com o deploy na mesma"
if errorlevel 2 goto :erro
:testesfeitos

:: ---------------------------------------------------------------- 6
echo.
echo [6/7] Deploy para o servidor
git remote get-url producao >nul 2>&1
if not errorlevel 1 goto :temremoto
echo       Esta copia nao tem o remoto "producao" (o deploy.bat faz git push producao main).
echo       No PC antigo ves o endereco com:  git remote -v
set "REMOTO="
set /p "REMOTO=      Cola o endereco do remoto producao (Enter para cancelar): "
if "%REMOTO%"=="" ( echo Deploy cancelado. & goto :erro )
git remote add producao "%REMOTO%" || ( echo ERRO ao juntar o remoto producao & goto :erro )
:temremoto
call deploy.bat
if errorlevel 1 ( echo ERRO no deploy & goto :erro )

:: ---------------------------------------------------------------- 7
echo.
echo [7/7] Agente no Raspberry
echo       O programa do agente mudou e tem de ser atualizado no Raspberry.
echo       Se este PC estiver na mesma rede que o Raspberry, faco-o ja por SSH.
choice /c SN /m "      Atualizar o Raspberry agora"
if errorlevel 2 goto :semraspberry
set "PI=ateneya@raspberrypi.local"
set /p "PI=      Utilizador@endereco do Raspberry [ateneya@raspberrypi.local]: "
if "%PI%"=="" set "PI=ateneya@raspberrypi.local"
ssh -t -o StrictHostKeyChecking=accept-new %PI% "cd ~/printer-agent && set -a && . ./.env && set +a && export NVM_DIR=$HOME/.nvm && [ -s $NVM_DIR/nvm.sh ] && . $NVM_DIR/nvm.sh; curl -fsS -H \"Authorization: Bearer $PRINT_AGENT_TOKEN\" \"${APP_URL%%/}/api/print-agent/agente.mjs\" -o agent.novo.mjs && node --check agent.novo.mjs && mv agent.novo.mjs agent.mjs && sudo systemctl restart printer-agent && echo AGENTE ATUALIZADO"
if errorlevel 1 (
    echo       Nao consegui atualizar por SSH. Faz no Raspberry: Impressoras ^> Agente de impressao
    echo       ^> "Atualizar o programa do agente" ^> copiar o comando e colar no terminal dele.
)
goto :fim
:semraspberry
echo       Nao te esquecas: no backoffice, Impressoras ^> Agente de impressao ^>
echo       "Atualizar o programa do agente" ^> copiar o comando e colar no terminal do Raspberry.

:fim
echo.
echo ================================================
echo   TUDO FEITO
echo   Falta: uma venda de teste em cada posto
echo   (2 imperiais + 1 bifana = 3 senhas e a conta).
echo ================================================
git log --oneline -2
pause
endlocal
exit /b 0

:erro
echo.
echo ================================================
echo   PAROU - ve a mensagem acima.
echo ================================================
pause
endlocal
exit /b 1
