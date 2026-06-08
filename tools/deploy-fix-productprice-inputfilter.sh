#!/usr/bin/env bash
# Fix salvataggio ProductPrice (subpanel Prezzi): InputFilter + prepareProductPriceInput.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-productprice-inputfilter-9999/tools/deploy-fix-productprice-inputfilter.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/fix-productprice-inputfilter-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

FILES=(
  "custom/Espo/Custom/Services/IvaDualPriceSync.php"
  "custom/Espo/Custom/Classes/Record/ProductPrice/InputFilter.php"
  "custom/Espo/Custom/Classes/FieldValidators/ProductPrice/Price/RequiredOrDualIva.php"
  "custom/Espo/Custom/Classes/Record/Hooks/ProductPrice/EarlyBeforeSavePrepare.php"
  "custom/Espo/Custom/Hooks/ProductPrice/DualIvaPricing.php"
  "custom/Espo/Custom/Resources/metadata/entityDefs/ProductPrice.json"
  "custom/Espo/Custom/Resources/metadata/recordDefs/ProductPrice.json"
)

echo "=== Fix ProductPrice InputFilter → ${CRM_ROOT} ==="

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
echo "Fatto. Ctrl+F5 e riprova a modificare un prezzo nel subpanel Prezzi."
