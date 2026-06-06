#!/usr/bin/env bash
# Contratto Articoli: popola Prezzo di Listino e Prezzo Codice alla selezione prodotto.
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/productprice-dual-iva-listino-codice-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}" || exit 1

TS="$(date +%Y%m%d-%H%M%S)"
BK="custom/backup-layouts/quote-item-catalog-prices-${TS}"
mkdir -p "${BK}"

for f in \
  custom/Espo/Custom/Services/QuotePricingCalculator.php \
  custom/Espo/Custom/Tools/Quote/Api/PostGetItemCatalogPrices.php \
  custom/Espo/Custom/Resources/routes.json \
  custom/Espo/Custom/Resources/client/custom/src/views/quote/fields/item-list.js \
  custom/Espo/Custom/Resources/client/custom/src/views/quote/record/item.js \
  client/custom/src/views/quote/fields/item-list.js \
  client/custom/src/views/quote/record/item.js
do
  cp -a "${f}" "${BK}/" 2>/dev/null || true
done
echo "Backup: ${BK}/"

curl -fsSL "${BASE}/custom/Espo/Custom/Services/QuotePricingCalculator.php?t=$(date +%s)" \
  -o custom/Espo/Custom/Services/QuotePricingCalculator.php
curl -fsSL "${BASE}/custom/Espo/Custom/Tools/Quote/Api/PostGetItemCatalogPrices.php?t=$(date +%s)" \
  -o custom/Espo/Custom/Tools/Quote/Api/PostGetItemCatalogPrices.php
curl -fsSL "${BASE}/custom/Espo/Custom/Resources/routes.json?t=$(date +%s)" \
  -o custom/Espo/Custom/Resources/routes.json
curl -fsSL "${BASE}/custom/Espo/Custom/Resources/client/custom/src/views/quote/fields/item-list.js?t=$(date +%s)" \
  -o custom/Espo/Custom/Resources/client/custom/src/views/quote/fields/item-list.js
curl -fsSL "${BASE}/custom/Espo/Custom/Resources/client/custom/src/views/quote/record/item.js?t=$(date +%s)" \
  -o custom/Espo/Custom/Resources/client/custom/src/views/quote/record/item.js
curl -fsSL "${BASE}/client/custom/src/views/quote/fields/item-list.js?t=$(date +%s)" \
  -o client/custom/src/views/quote/fields/item-list.js
curl -fsSL "${BASE}/client/custom/src/views/quote/record/item.js?t=$(date +%s)" \
  -o client/custom/src/views/quote/record/item.js

echo "OK file deployati"

php -l custom/Espo/Custom/Services/QuotePricingCalculator.php
php -l custom/Espo/Custom/Tools/Quote/Api/PostGetItemCatalogPrices.php

php command.php rebuild
php command.php clear-cache 2>/dev/null || true
rm -rf data/cache/* 2>/dev/null || true
chmod -R u+rwX data/cache 2>/dev/null || true

echo ""
echo "Deploy completato."
echo "Test: apri un contratto, seleziona listino + prodotto → Prezzo di Listino e Prezzo Codice compilati."
echo "Rollback: ripristina da ${BK}/ && php command.php rebuild"
