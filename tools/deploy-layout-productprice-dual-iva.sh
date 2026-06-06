#!/usr/bin/env bash
# Deploy layout ProductPrice (dual IVA, senza periodo / qty min) + rebuild.
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/productprice-dual-iva-listino-codice-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}" || exit 1
LAYOUT_DIR="custom/Espo/Custom/Resources/layouts/ProductPrice"
I18N="custom/Espo/Custom/Resources/i18n/it_IT/ProductPrice.json"
mkdir -p "${LAYOUT_DIR}" "$(dirname "${I18N}")"

for f in detail.json detailSmall.json list.json listForProduct.json listForPriceBook.json; do
  curl -fsSL "${BASE}/${LAYOUT_DIR}/${f}?t=$(date +%s)" -o "${LAYOUT_DIR}/${f}"
  echo "OK ${LAYOUT_DIR}/${f}"
done
curl -fsSL "${BASE}/custom/Espo/Custom/Resources/layouts/Product/bottomPanelsDetail.json?t=$(date +%s)" \
  -o custom/Espo/Custom/Resources/layouts/Product/bottomPanelsDetail.json
echo "OK custom/Espo/Custom/Resources/layouts/Product/bottomPanelsDetail.json"
curl -fsSL "${BASE}/custom/Espo/Custom/Resources/layouts/Quote/listForProduct.json?t=$(date +%s)" \
  -o custom/Espo/Custom/Resources/layouts/Quote/listForProduct.json
echo "OK custom/Espo/Custom/Resources/layouts/Quote/listForProduct.json"
curl -fsSL "${BASE}/custom/Espo/Custom/Resources/metadata/clientDefs/Product.json?t=$(date +%s)" \
  -o custom/Espo/Custom/Resources/metadata/clientDefs/Product.json
echo "OK custom/Espo/Custom/Resources/metadata/clientDefs/Product.json"
curl -fsSL "${BASE}/custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json?t=$(date +%s)" \
  -o custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json
echo "OK custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json"
curl -fsSL "${BASE}/custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json?t=$(date +%s)" \
  -o custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json
echo "OK entityDefs Quote.json"
curl -fsSL "${BASE}/${I18N}?t=$(date +%s)" -o "${I18N}"
echo "OK ${I18N}"

php command.php rebuild
php command.php clear-cache 2>/dev/null || true
rm -rf data/cache/*
echo "Layout ProductPrice aggiornati. Ctrl+F5."
