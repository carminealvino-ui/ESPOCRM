#!/usr/bin/env bash
# Pannello Prezzo Product con data inizio validità → subpanel Prezzi (ProductPrice).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/product-prezzo-validita-9999/tools/deploy-product-prezzo-validita.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/product-prezzo-validita-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

FILES=(
  "custom/Espo/Custom/Services/ProductPriceTimeline.php"
  "custom/Espo/Custom/Hooks/Product/PreparePriceTimeline.php"
  "custom/Espo/Custom/Hooks/Product/SyncPriceTimeline.php"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Product.json"
  "custom/Espo/Custom/Resources/layouts/Product/detail.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Product.json"
)

echo "=== Deploy prezzo validità Product → ${CRM_ROOT} ==="

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
echo "Fatto. Rebuild eseguito. Modificare listPrice/prezzoCodice + data inizio validità sul Product."
