#!/usr/bin/env bash
# Fix prezzi dual IVA listino (SQL diretto) + layout etichette + rebuild.
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/productprice-dual-iva-listino-codice-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}" || exit 1
mkdir -p tools custom/Espo/Custom/Resources/layouts/ProductPrice

curl -fsSL "${BASE}/tools/fix-prezzi-listino-completo.php?t=$(date +%s)" -o tools/fix-prezzi-listino-completo.php
curl -fsSL "${BASE}/custom/Espo/Custom/Resources/layouts/ProductPrice/listForPriceBook.json?t=$(date +%s)" \
  -o custom/Espo/Custom/Resources/layouts/ProductPrice/listForPriceBook.json
curl -fsSL "${BASE}/custom/Espo/Custom/Resources/layouts/PriceBook/detail.json?t=$(date +%s)" \
  -o custom/Espo/Custom/Resources/layouts/PriceBook/detail.json

echo "=== Fix prezzi dual IVA (SQL) ==="
php tools/fix-prezzi-listino-completo.php "$@"

echo ""
echo "=== Rebuild + cache ==="
php command.php rebuild
php command.php clear-cache 2>/dev/null || true
rm -rf data/cache/*

echo "Completato."
