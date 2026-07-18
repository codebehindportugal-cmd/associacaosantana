@echo off
cd /d C:\laragon\www\associacaosantana

echo === COPIAR VAPID KEYS PARA SERVIDOR ===
echo.

php _gen_vapid_script.php
if %ERRORLEVEL% NEQ 0 ( pause & exit /b 1 )

echo A copiar e executar no servidor...
scp -o StrictHostKeyChecking=no _set_vapid.sh plesk-dev:/tmp/_set_vapid.sh
ssh -o StrictHostKeyChecking=no plesk-dev "bash /tmp/_set_vapid.sh && rm -f /tmp/_set_vapid.sh"

del _set_vapid.sh 2>nul

echo.
echo === VAPID keys copiadas para o servidor ===
pause
