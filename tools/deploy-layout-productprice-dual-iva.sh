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
curl -fsSL "${BASE}/${I18N}?t=$(date +%s)" -o "${I18N}"
echo "OK ${I18N}"

php command.php rebuild
php command.php clear-cache 2>/dev/null || true
rm -rf data/cache/*
echo "Layout ProductPrice aggiornati. Ctrl+F5."
