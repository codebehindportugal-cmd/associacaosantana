@echo off
setlocal enabledelayedexpansion
title Configurar agente de impressao
cd /d "%~dp0.."

echo ==========================================
echo  Agente de impressao - configuracao
echo ==========================================
echo.

set "NODE=node"
if exist "node\node.exe" set "NODE=%~dp0..\node\node.exe"
"%NODE%" -v >/dev/null 2>nul
if errorlevel 1 (
  echo [!] Node.js nao encontrado neste computador.
  echo     Instala em https://nodejs.org ^(versao LTS^) ou copia uma pasta
  echo     "node" com o node.exe para dentro de local-printer-agent.
  echo.
  pause
  exit /b 1
)

set "APP_URL=https://ardcsantana.ateneya.com"
set /p APP_URL=Endereco do site [%APP_URL%]: 

set "TOKEN="
set /p TOKEN=Token do agente (PRINT_AGENT_TOKEN): 
if "%TOKEN%"=="" (
  echo [!] O token e obrigatorio. Ve o valor no .env do servidor.
  pause
  exit /b 1
)

set "AGENTE="
set /p AGENTE=Nome deste posto (ex: caixa-1): 
if "%AGENTE%"=="" (
  echo [!] O nome do posto e obrigatorio. Tem de ser igual ao campo
  echo     "Posto (agente)" das impressoras no backoffice.
  pause
  exit /b 1
)

(
  echo APP_URL=%APP_URL%
  echo PRINT_AGENT_TOKEN=%TOKEN%
  echo POLL_SECONDS=3
  echo PRINT_DELAY_MS=200
  echo PRINT_CODEPAGE=cp860
  echo AGENTE=%AGENTE%
) > .env

echo.
echo [ok] Ficheiro .env criado para o posto "%AGENTE%".
echo      Agora usa iniciar-agente.bat para o por a trabalhar.
echo.
pause
