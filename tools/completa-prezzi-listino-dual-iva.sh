#!/usr/bin/env bash
# Install campi dual IVA + backfill prezzi + etichette listino (tutto in un colpo).
#
#   cd ~/public_html/crm/mec-group && curl -fsSL ".../completa-prezzi-listino-dual-iva.sh?t=$(date +%s)" | bash
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/productprice-dual-iva-listino-codice-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}" || exit 1

fetch() {
  curl -fsSL "${BASE}/$1?t=$(date +%s)" -o "$1"
}

mkdir -p tools custom/Espo/Custom/Resources/layouts/ProductPrice

fetch tools/install-productprice-dual-iva-fields.php
fetch tools/backfill-productprice-dual-iva-from-price.php
fetch custom/Espo/Custom/Resources/layouts/ProductPrice/listForPriceBook.json

echo "=== 1/3 Install campi dual IVA su ProductPrice ==="
php tools/install-productprice-dual-iva-fields.php

echo ""
echo "=== 2/3 Backfill prezzi da campo price ==="
php tools/backfill-productprice-dual-iva-from-price.php --verbose

echo ""
echo "=== 3/3 Rebuild + cache (i18n PriceBook taxCode) ==="
php command.php rebuild
php command.php clear-cache 2>/dev/null || true
rm -rf data/cache/*

echo ""
echo "Fatto. Ctrl+F5 su ARIEL Energia → colonne prezzo e etichette italiane."
