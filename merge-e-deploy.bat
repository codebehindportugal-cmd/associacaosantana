@echo off
:: Junta a branch feature/caucao-metro a main e faz o deploy
cd /d C:\laragon\www\associacaosantana
if exist .git\index.lock del .git\index.lock

echo === Mudar para main ===
git checkout main
if %ERRORLEVEL% NEQ 0 ( echo ERRO ao mudar para main & pause & exit /b 1 )

echo === Juntar feature/caucao-metro ===
git merge --no-edit feature/caucao-metro
if %ERRORLEVEL% NEQ 0 ( echo ERRO no merge - NAO FECHES, avisa o Claude & pause & exit /b 1 )

echo === Push para GitHub ===
git push origin main

echo === Deploy ===
call deploy.bat
