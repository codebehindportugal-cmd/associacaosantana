@echo off
title Arranque automatico do agente
set "ALVO=%~dp0iniciar-agente.bat"
schtasks /create /tn "Agente de impressao ARDC" /tr "\"%ALVO%\"" /sc onlogon /rl highest /f
if errorlevel 1 (
  echo [!] Nao foi possivel criar a tarefa. Corre este ficheiro como administrador.
) else (
  echo [ok] O agente passa a arrancar sozinho ao iniciar sessao neste computador.
)
pause
