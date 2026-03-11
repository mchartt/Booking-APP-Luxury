@echo off
echo ========================================
echo   Luxury Hotel - Avvio Server
echo ========================================
echo.

cd /d "%~dp0"

:: Verifica se node_modules esiste
if not exist "node_modules" (
    echo Installazione dipendenze...
    call npm install
    echo.
)

echo Avvio server su http://localhost:3000
echo Premi CTRL+C per fermare il server
echo.

node server.js
pause
