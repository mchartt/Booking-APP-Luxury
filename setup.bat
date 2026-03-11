@echo off
REM ===== LUXURY HOTEL - SETUP SCRIPT FOR WINDOWS =====
REM Script di configurazione automatica per Windows

cls
echo.
echo ╔════════════════════════════════════════════╗
echo ║  🏨 LUXURY HOTEL - Setup Wizard            ║
echo ║  Windows Edition                           ║
echo ╚════════════════════════════════════════════╝
echo.

REM Check Node.js
node -v >nul 2>&1
if errorlevel 1 (
    echo ❌ Node.js non trovato!
    echo    Scarica da: https://nodejs.org/
    pause
    exit /b 1
)

for /f "tokens=*" %%i in ('node -v') do set NODE_VERSION=%%i
echo ✓ Node.js trovato: %NODE_VERSION%

REM Check npm
npm -v >nul 2>&1
if errorlevel 1 (
    echo ❌ npm non trovato!
    pause
    exit /b 1
)

for /f "tokens=*" %%i in ('npm -v') do set NPM_VERSION=%%i
echo ✓ npm trovato: %NPM_VERSION%
echo.

REM Install dependencies
echo 📦 Installazione dipendenze...
call npm install
if errorlevel 1 (
    echo ❌ Errore nell'installazione delle dipendenze
    pause
    exit /b 1
)
echo ✓ Dipendenze installate con successo
echo.

cls
echo.
echo ╔════════════════════════════════════════════╗
echo ║  ✨ Setup completato!                      ║
echo ╚════════════════════════════════════════════╝
echo.
echo Prossimi passi:
echo.
echo 1️⃣  Avvia il backend:
echo    npm start
echo.
echo 2️⃣  Apri il frontend nel browser:
echo    - Live Server: Clicca destro su index.html
echo    - Seleziona "Open with Live Server"
echo.
echo 3️⃣  Inizia a fare prenotazioni! 🎉
echo.
echo API disponibile su: http://localhost:3000/api
echo.
pause
