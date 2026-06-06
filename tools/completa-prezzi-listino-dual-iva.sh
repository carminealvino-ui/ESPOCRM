#!/usr/bin/env bash
# Valorizza prezzi dual IVA nel listino + etichette italiane (dopo taxCode OK).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL ".../tools/completa-prezzi-listino-dual-iva.sh?t=$(date +%s)" | bash
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/productprice-dual-iva-listino-codice-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}" || exit 1

curl -fsSL "${BASE}/tools/backfill-productprice-dual-iva-from-price.php?t=$(date +%s)" \
  -o tools/backfill-productprice-dual-iva-from-price.php

echo "=== Backfill prezzi dual IVA da campo price ==="
php tools/backfill-productprice-dual-iva-from-price.php

echo ""
echo "=== Rebuild (etichette i18n) + cache ==="
php command.php rebuild
php command.php clear-cache 2>/dev/null || true
rm -rf data/cache/*

echo ""
echo "Fatto. Ctrl+F5 su Listino ARIEL Energia → colonne prezzo valorizzate ed etichette in italiano."
