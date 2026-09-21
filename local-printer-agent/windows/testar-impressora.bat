@echo off
title Testar impressora de rede
cd /d "%~dp0.."

set "NODE=node"
if exist "node\node.exe" set "NODE=%~dp0..\node\node.exe"

echo ==========================================
echo  Teste directo a impressora de rede
echo  (nao passa pelo site - so testa PC ^<-^> impressora)
echo ==========================================
echo.
echo IP deste computador:
ipconfig | findstr /i "IPv4"
echo.
set "IP="
set /p IP=IP da impressora (ex: 192.168.1.50): 
if "%IP%"=="" exit /b 1

echo.
ping -n 2 %IP%
echo.
"%NODE%" agent.mjs --teste %IP%
echo.
pause
