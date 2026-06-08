#!/usr/bin/env bash
# Fix errore inserimento voce contratto (Quote/getItemCatalogPrices + formula number\round).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-quote-voce-contratto-9999/tools/deploy-fix-quote-voce-contratto.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/fix-quote-voce-contratto-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

FILES=(
  "custom/Espo/Custom/Services/QuotePricingCalculator.php"
  "custom/Espo/Custom/Tools/Quote/Api/PostGetItemCatalogPrices.php"
  "custom/Espo/Custom/Resources/routes.json"
  "custom/Espo/Custom/Resources/metadata/formula/Quote.json"
)

echo "=== Fix voce contratto (Quote) → ${CRM_ROOT} ==="

for rel in "${FILES[@]}"; do
  target="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${target}")"
  curl -fsSL -o "${target}" "${BASE}/${rel}?t=$(date +%s)"
  echo "OK ${rel}"
done

CLIENT_FILES=(
  "client/custom/src/handlers/quote/catalog-prices.js"
  "client/custom/src/views/quote/record/item.js"
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
echo "Fatto. Ctrl+F5 e riprova ad aggiungere una voce al contratto."
