#!/usr/bin/env bash
# Deploy listino doppio prezzo IVA (ProductPrice / PriceBook).
#
# Sul server produzione (con clone git):
#   CRM=~/public_html/crm/mec-group
#   GIT=~/ESPOCRM-git
#   BR=cursor/productprice-dual-iva-listino-codice-9999
#   bash "$GIT/tools/deploy-productprice-dual-iva-listino.sh"
#
# Oppure via curl (senza clone):
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/productprice-dual-iva-listino-codice-9999/tools/deploy-productprice-dual-iva-listino.sh?t=$(date +%s)" | bash
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
GIT_ROOT="${GIT_ROOT:-$HOME/ESPOCRM-git}"
BRANCH="${BRANCH:-cursor/productprice-dual-iva-listino-codice-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}" || exit 1

copy_from_git() {
  local rel="$1"
  local dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  if [[ -f "${GIT_ROOT}/${rel}" ]]; then
    cp -a "${GIT_ROOT}/${rel}" "${dest}"
  else
    curl -fsSL "${BASE}/${rel}?t=$(date +%s)" -o "${dest}"
  fi
  echo "OK ${rel}"
}

echo "=== Deploy listino doppio prezzo IVA (branch ${BRANCH}) ==="

FILES=(
  "custom/Espo/Custom/Hooks/ProductPrice/DualIvaPricing.php"
  "custom/Espo/Custom/Services/IvaDualPriceSync.php"
  "custom/Espo/Custom/Services/QuotePricingCalculator.php"
  "custom/Espo/Custom/Resources/metadata/entityDefs/PriceBook.json"
  "custom/Espo/Custom/Resources/metadata/clientDefs/PriceBook.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/ProductPrice.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Product.json"
  "custom/Espo/Custom/Resources/layouts/PriceBook/detail.json"
  "custom/Espo/Custom/Resources/layouts/ProductPrice/detail.json"
  "custom/Espo/Custom/Resources/layouts/ProductPrice/detailSmall.json"
  "custom/Espo/Custom/Resources/layouts/ProductPrice/listForPriceBook.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/PriceBook.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/ProductPrice.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Product.json"
  "tools/sync-listino-prodotti.php"
  "tools/test-iva-dual-price-sync.php"
  "tools/backfill-productprice-dual-iva-from-price.php"
)

for rel in "${FILES[@]}"; do
  copy_from_git "${rel}"
done

php command.php rebuild
php command.php clearCache 2>/dev/null || true
rm -rf data/cache/*

echo ""
echo "Fatto. In admin: su ogni Listino (PriceBook) imposta TaxCode obbligatorio."
echo "Test opzionale: php tools/test-iva-dual-price-sync.php"
