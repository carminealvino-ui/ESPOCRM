#!/usr/bin/env bash
# Applica fix listino dual IVA + taxCode + backfill prezzi (produzione).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL ".../tools/applica-listino-dual-iva-produzione.sh?t=$(date +%s)" | bash
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/productprice-dual-iva-listino-codice-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}" || exit 1

echo "=== 1/4 Deploy file listino dual IVA ==="
curl -fsSL "${BASE}/tools/deploy-productprice-dual-iva-listino.sh?t=$(date +%s)" -o /tmp/deploy-listino-dual-iva.sh
bash /tmp/deploy-listino-dual-iva.sh

echo ""
echo "=== 2/4 Verifica metadata ==="
php tools/verifica-listino-dual-iva.php || true

echo ""
echo "=== 3/4 Imposta TaxCode IVA10 su listini senza codice ==="
php tools/set-pricebook-tax-code.php --tax-code=IVA10 --all-missing

echo ""
echo "=== 4/4 Backfill prezzi dual IVA da campo price ==="
php tools/backfill-productprice-dual-iva-from-price.php

echo ""
echo "Fatto. Ctrl+F5 su Listino Prezzi → deve comparire Imposta - Codice e colonne prezzo valorizzate."
echo "Se taxCode ancora assente: Amministrazione → Layout Manager → PriceBook → Detail → aggiungi taxCode."
