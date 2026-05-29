#!/usr/bin/env bash
# Deploy SOLO PHP prezzi contratto — NON tocca layout, clientDefs, entityDefs.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL ".../cursor/quote-prezzi-iva-inclusa-9999/tools/deploy-contratto-prezzi-curl.sh?t=$(date +%s)" -o /tmp/deploy-prezzi.sh
#   bash /tmp/deploy-prezzi.sh
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/quote-prezzi-iva-inclusa-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}" || exit 1

mkdir -p "${CRM_ROOT}/tools"
curl -fsSL "${BASE}/tools/backup-quote-layouts.sh?t=$(date +%s)" -o "${CRM_ROOT}/tools/backup-quote-layouts.sh" 2>/dev/null || true
bash "${CRM_ROOT}/tools/backup-quote-layouts.sh" 2>/dev/null || true

FILES=(
  "tools/backup-quote-layouts.sh"
  "custom/Espo/Custom/Services/QuotePricingCalculator.php"
  "custom/Espo/Custom/Hooks/Quote/SyncContractPricing.php"
  "tools/fix-contratto-importo-minusplus-standalone.php"
)

for rel in "${FILES[@]}"; do
  dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=$(date +%s)" -o "${dest}"
  echo "OK ${rel}"
done

rm -f "${CRM_ROOT}/client/custom/src/handlers/quote/calculation-handler.js"
rm -f "${CRM_ROOT}/custom/Espo/Custom/Hooks/Quote/SyncContractPricingAfterSave.php"

php command.php rebuild
rm -rf data/cache/*
echo ""
echo "Solo PHP prezzi. Layout Contratto NON modificato."
echo "Poi: php tools/fix-contratto-importo-minusplus-standalone.php --name=\"POLTRONI MARZIA\" --importo=4500"
