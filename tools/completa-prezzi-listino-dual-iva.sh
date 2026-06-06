#!/usr/bin/env bash
# Fix prezzi dual IVA — scarica SEMPRE lo script SQL aggiornato ed esegue.
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/productprice-dual-iva-listino-codice-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"
SCRIPT="tools/fix-prezzi-listino-completo.php"

cd "${CRM_ROOT}" || exit 1
mkdir -p tools custom/Espo/Custom/Resources/layouts/ProductPrice

rm -f "${SCRIPT}"
curl -fsSL "${BASE}/${SCRIPT}?t=$(date +%s)" -o "${SCRIPT}"

if ! grep -q 'sql-20260606' "${SCRIPT}"; then
  echo "ERRORE: file scaricato non è la versione SQL. Controlla connessione GitHub."
  head -5 "${SCRIPT}"
  exit 1
fi

curl -fsSL "${BASE}/custom/Espo/Custom/Resources/layouts/ProductPrice/listForPriceBook.json?t=$(date +%s)" \
  -o custom/Espo/Custom/Resources/layouts/ProductPrice/listForPriceBook.json
curl -fsSL "${BASE}/custom/Espo/Custom/Resources/layouts/PriceBook/detail.json?t=$(date +%s)" \
  -o custom/Espo/Custom/Resources/layouts/PriceBook/detail.json

echo "=== Esecuzione fix SQL ==="
php "${SCRIPT}" "$@"

echo ""
echo "=== Rebuild + cache ==="
php command.php rebuild
php command.php clear-cache 2>/dev/null || true
rm -rf data/cache/*

echo "Completato. Ctrl+F5 su ARIEL Energia."
