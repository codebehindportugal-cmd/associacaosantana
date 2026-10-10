@echo off
:: ============================================================
::  Envia as alteracoes do POS (token do agente, ecras baixos e
::  juntar senhas por seccao) para o GitHub.
::
::  Poe este ficheiro e o santana-alteracoes.patch na MESMA pasta
::  (ex.: Transferencias) e faz duplo clique.
:: ============================================================
setlocal
set "PASTA=C:\laragon\www\associacaosantana"
set "REPO=https://github.com/codebehindportugal-cmd/associacaosantana.git"
set "PATCH=%~dp0santana-alteracoes.patch"

if not exist "%PATCH%" (
    echo ERRO: nao encontro o santana-alteracoes.patch ao lado deste ficheiro.
    echo Poe os dois ficheiros na mesma pasta e tenta outra vez.
    pause & exit /b 1
)

:: 1. Ter o projeto na pasta do Laragon
if not exist "%PASTA%\.git" (
    if exist "%PASTA%" (
        dir /b "%PASTA%" | findstr . >nul && (
            echo ERRO: %PASTA% existe mas nao e um repositorio git.
            echo Muda-lhe o nome ou apaga-a e corre isto outra vez.
            pause & exit /b 1
        )
        rmdir "%PASTA%"
    )
    echo [1/4] A copiar o projeto do GitHub...
    git clone "%REPO%" "%PASTA%"
    if errorlevel 1 ( echo ERRO no git clone & pause & exit /b 1 )
) else (
    echo [1/4] Projeto ja existe em %PASTA%.
)

cd /d "%PASTA%"
if exist .git\index.lock del .git\index.lock

:: 2. Estar no main atualizado
echo [2/4] A atualizar o main...
git checkout main
if errorlevel 1 ( echo ERRO ao mudar para main & pause & exit /b 1 )
git pull --ff-only origin main
if errorlevel 1 ( echo ERRO no git pull - ha alteracoes locais por resolver & pause & exit /b 1 )

:: 3. Aplicar o commit (se ainda nao estiver aplicado)
git log --oneline -20 | findstr /c:"token do agente no backoffice" >nul
if not errorlevel 1 (
    echo [3/4] O commit ja esta aplicado - salto este passo.
) else (
    echo [3/4] A aplicar o commit...
    git am --keep-cr "%PATCH%"
    if errorlevel 1 (
        echo ERRO ao aplicar o patch. A desfazer...
        git am --abort
        pause & exit /b 1
    )
)

:: 4. Enviar para o GitHub
echo [4/4] A enviar para o GitHub...
git push origin main
if errorlevel 1 ( echo ERRO no push & pause & exit /b 1 )

echo.
echo ============================================
echo   ENVIADO PARA O GITHUB
echo   Para por no site: composer install, npm install
echo   (so na primeira vez) e depois deploy.bat
echo ============================================
git log --oneline -3
pause
endlocal
