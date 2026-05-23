#!/bin/bash
# Verifica rapida se CSS core EspoCRM sono stati modificati di recente
# Eseguire dalla root del CRM: bash tools/check-espo-core-css.sh

set -e

ROOT="${1:-.}"
cd "$ROOT"

echo "=== Cartelle CSS core (date modifica) ==="
if [ -d client/css ]; then
    find client/css -maxdepth 3 -type f \( -name "*.css" -o -name "*.less" \) -printf '%TY-%Tm-%Td %TH:%TM  %p\n' 2>/dev/null | sort -r | head -25
else
    echo "Cartella client/css non trovata"
fi

echo ""
echo "=== CSS custom progetto ==="
find client/custom/css -type f 2>/dev/null || echo "(nessuno)"

echo ""
echo "=== client.json (cssList) ==="
if [ -f custom/Espo/Custom/Resources/metadata/app/client.json ]; then
    cat custom/Espo/Custom/Resources/metadata/app/client.json
else
    echo "File non trovato"
fi

echo ""
echo "Se vedi file in client/css/espo modificati di recente senza aggiornamento Espo,"
echo "ripristinali dalla versione ufficiale del tuo EspoCRM."
