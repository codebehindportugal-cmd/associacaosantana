@echo off
title Agente de impressao
cd /d "%~dp0.."

if not exist ".env" (
  echo [!] Falta o .env. Corre primeiro windows\instalar-agente.bat
  pause
  exit /b 1
)

set "NODE=node"
if exist "node\node.exe" set "NODE=%~dp0..\node\node.exe"

:loop
"%NODE%" agent.mjs
echo.
echo [!] O agente parou. A reiniciar daqui a 5 segundos. Fecha a janela para sair.
timeout /t 5 >nul
goto loop
