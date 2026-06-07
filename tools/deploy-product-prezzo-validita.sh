#!/usr/bin/env bash
# Product completo: elenco catalogo + pannello prezzo dual IVA + timeline Prezzi.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/product-prezzo-validita-9999/tools/deploy-product-prezzo-validita.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/product-prezzo-validita-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

FILES=(
  "custom/Espo/Custom/Hooks/Product/BeforeSave.php"
  "custom/Espo/Custom/Hooks/Product/DualIvaPricing.php"
  "custom/Espo/Custom/Hooks/Product/PreparePriceTimeline.php"
  "custom/Espo/Custom/Hooks/Product/SyncPriceTimeline.php"
  "custom/Espo/Custom/Services/ProductPriceBookResolver.php"
  "custom/Espo/Custom/Services/IvaDualPriceSync.php"
  "custom/Espo/Custom/Services/ProductPriceTimeline.php"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Product.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/ProductCategory.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/ProductBrand.json"
  "custom/Espo/Custom/Hooks/ProductPrice/DualIvaPricing.php"
  "custom/Espo/Custom/Classes/Record/Hooks/ProductPrice/EarlyBeforeSavePrepare.php"
  "custom/Espo/Custom/Resources/metadata/recordDefs/ProductPrice.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/ProductPrice.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/PriceBook.json"
  "custom/Espo/Custom/Resources/metadata/formula/Product.json"
  "custom/Espo/Custom/Resources/layouts/Product/detail.json"
  "custom/Espo/Custom/Resources/layouts/Product/detailSmall.json"
  "custom/Espo/Custom/Resources/layouts/Product/list.json"
  "custom/Espo/Custom/Resources/layouts/ProductCategory/detail.json"
  "custom/Espo/Custom/Resources/layouts/ProductBrand/detail.json"
  "custom/Espo/Custom/Resources/layouts/ProductPrice/listForProduct.json"
  "custom/Espo/Custom/Resources/layouts/ProductPrice/detailSmall.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/ProductPrice.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Product.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/ProductCategory.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/ProductBrand.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/PriceBook.json"
)

echo "=== Deploy Product (catalogo + prezzi) → ${CRM_ROOT} ==="

for rel in "${FILES[@]}"; do
  target="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${target}")"
  curl -fsSL -o "${target}" "${BASE}/${rel}?t=$(date +%s)"
  echo "OK ${rel}"
done

CLIENT_FILES=(
  "client/custom/src/views/fields/date-numeric.js"
  "client/custom/src/views/product-price/fields/validity-numeric.js"
)

for rel in "${CLIENT_FILES[@]}"; do
  for prefix in \
    "${CRM_ROOT}/${rel}" \
    "${CRM_ROOT}/custom/Espo/Custom/Resources/${rel}" \
    "${CRM_ROOT}/custom/Espo/Custom/${rel}"
  do
    mkdir -p "$(dirname "${prefix}")"
    curl -fsSL -o "${prefix}" "${BASE}/${rel}?t=$(date +%s)"
  done
  echo "OK ${rel} (client paths)"
done

if [[ -f "${CRM_ROOT}/clear_cache.php" ]]; then
  (cd "${CRM_ROOT}" && php clear_cache.php && php rebuild.php)
elif [[ -f "${CRM_ROOT}/command.php" ]]; then
  (cd "${CRM_ROOT}" && php command.php rebuild && php command.php clearCache)
fi

echo ""
echo "Fatto. Rebuild eseguito."
echo "Impostare Listino prezzi su ogni Brand (es. ARIEL → ARIEL Energia)."
echo "SQL opzionale: database/2026-06-07-product-brand-price-book.sql"
echo "SQL opzionale: database/2026-06-07-product-price-dual-iva.sql"
