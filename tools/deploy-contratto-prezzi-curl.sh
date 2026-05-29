#!/usr/bin/env bash
# Deploy file PHP + client contratto/prezzi da GitHub (senza git clone).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/quote-prezzi-iva-inclusa-9999/tools/deploy-contratto-prezzi-curl.sh?t=$(date +%s)" -o /tmp/deploy-contratto-prezzi.sh
#   bash /tmp/deploy-contratto-prezzi.sh
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/quote-prezzi-iva-inclusa-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}" || exit 1

FILES=(
  "custom/Espo/Custom/Services/QuotePricingCalculator.php"
  "custom/Espo/Custom/Hooks/Quote/SyncContractPricing.php"
  "custom/Espo/Custom/Hooks/Quote/SyncContractPricingAfterSave.php"
  "custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json"
  "custom/Espo/Custom/Resources/layouts/Quote/detail.json"
  "custom/Espo/Custom/Resources/layouts/Quote/edit.json"
  "tools/fix-contratto-importo-minusplus-standalone.php"
)

CLIENT_FILES=(
  "client/custom/src/handlers/quote/calculation-handler.js"
)

for rel in "${FILES[@]}"; do
  dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=$(date +%s)" -o "${dest}"
  echo "OK ${rel}"
done

for rel in "${CLIENT_FILES[@]}"; do
  dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=$(date +%s)" -o "${dest}"
  echo "OK ${rel}"
done

php command.php rebuild
rm -rf data/cache/*
echo "Rebuild fatto."
echo "Poi: php tools/fix-contratto-importo-minusplus-standalone.php --name=\"POLTRONI MARZIA\" --importo=4500"
