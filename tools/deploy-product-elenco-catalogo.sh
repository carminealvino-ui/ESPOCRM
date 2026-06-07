#!/usr/bin/env bash
# Campo elenco catalogo su Product (+ ProductCategory) e naming catalogo.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/product-elenco-catalogo-9999/tools/deploy-product-elenco-catalogo.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/product-elenco-catalogo-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

FILES=(
  "custom/Espo/Custom/Hooks/Product/BeforeSave.php"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Product.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/ProductCategory.json"
  "custom/Espo/Custom/Resources/metadata/formula/Product.json"
  "custom/Espo/Custom/Resources/layouts/Product/detail.json"
  "custom/Espo/Custom/Resources/layouts/Product/list.json"
  "custom/Espo/Custom/Resources/layouts/ProductCategory/detail.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Product.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/ProductCategory.json"
)

echo "=== Deploy elenco catalogo Product → ${CRM_ROOT} ==="

for rel in "${FILES[@]}"; do
  target="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${target}")"
  curl -fsSL -o "${target}" "${BASE}/${rel}?t=$(date +%s)"
  echo "OK ${rel}"
done

if [[ -f "${CRM_ROOT}/clear_cache.php" ]]; then
  (cd "${CRM_ROOT}" && php clear_cache.php && php rebuild.php)
elif [[ -f "${CRM_ROOT}/command.php" ]]; then
  (cd "${CRM_ROOT}" && php command.php rebuild && php command.php clearCache)
fi

echo ""
echo "Fatto. Rebuild eseguito. Compilare elencoCatalogo su prodotti e categorie."
