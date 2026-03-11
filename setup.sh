#!/bin/bash

# ===== LUXURY HOTEL - SETUP SCRIPT =====
# Script di configurazione automatica

echo "╔════════════════════════════════════════════╗"
echo "║  🏨 LUXURY HOTEL - Setup Wizard            ║"
echo "╚════════════════════════════════════════════╝"
echo ""

# Check Node.js
if ! command -v node &> /dev/null; then
    echo "❌ Node.js non trovato!"
    echo "   Scarica da: https://nodejs.org/"
    exit 1
fi

echo "✓ Node.js trovato: $(node -v)"

# Check npm
if ! command -v npm &> /dev/null; then
    echo "❌ npm non trovato!"
    exit 1
fi

echo "✓ npm trovato: $(npm -v)"
echo ""

# Install dependencies
echo "📦 Installazione dipendenze..."
if npm install; then
    echo "✓ Dipendenze installate con successo"
else
    echo "❌ Errore nell'installazione delle dipendenze"
    exit 1
fi

echo ""
echo "╔════════════════════════════════════════════╗"
echo "║  ✨ Setup completato!                      ║"
echo "╚════════════════════════════════════════════╝"
echo ""
echo "Prossimi passi:"
echo ""
echo "1️⃣  Avvia il backend:"
echo "   npm start"
echo ""
echo "2️⃣  Apri il frontend nel browser:"
echo "   - Live Server: Clicca destro su index.html → Open with Live Server"
echo "   - Oppure: Apri file:///path/to/index.html nel browser"
echo ""
echo "3️⃣  Inizia a fare prenotazioni! 🎉"
echo ""
echo "API disponibile su: http://localhost:3000/api"
echo ""
