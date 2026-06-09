#!/usr/bin/env bash
# Reset scheda Contratto: layout standard + solo «Crea articolo», niente hook prezzi/provvigioni.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL ".../tools/deploy-contratto-reset-pulito.sh?t=$(date +%s)" -o /tmp/reset-contratto.sh
#   bash /tmp/reset-contratto.sh
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/provvigioni-manuali-fase-a-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}" || exit 1

fetch() {
  local rel="$1"
  local dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=$(date +%s)" -o "${dest}"
  echo "OK ${rel}"
}

echo "=== Reset contratto pulito ==="

fetch "custom/Espo/Custom/Resources/layouts/Quote/detail.json"
fetch "custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json"
fetch "client/custom/src/views/quote/fields/item-list.js"
rm -f "${CRM_ROOT}/client/custom/src/custom-product-button.js"

HOOKS=(
  "custom/Espo/Custom/Hooks/Quote/SyncMinusPlus.php"
  "custom/Espo/Custom/Hooks/Quote/AfterSaveSyncContractTotals.php"
  "custom/Espo/Custom/Hooks/Quote/BeforeSave.php"
  "custom/Espo/Custom/Hooks/Quote/ProvvigioneConsolidata.php"
)

for h in "${HOOKS[@]}"; do
  if [[ -f "${CRM_ROOT}/${h}" ]]; then
    rm -f "${CRM_ROOT}/${h}"
    echo "RIMOSSO ${h}"
  fi
done

php command.php rebuild
rm -rf data/cache/*
echo "Fatto. Contratto: articoli + pulsante Crea prodotto (anche in visualizzazione)."
