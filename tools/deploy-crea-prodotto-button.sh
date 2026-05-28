#!/usr/bin/env bash
# Fix produzione: pulsante «Crea prodotto» — path corretto client/custom/src/
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL ".../tools/deploy-crea-prodotto-button.sh?t=$(date +%s)" -o /tmp/deploy-crea-prodotto.sh
#   bash /tmp/deploy-crea-prodotto.sh
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

echo "=== Deploy Crea prodotto (client/custom/src) ==="

fetch "client/custom/src/custom-product-button.js"
fetch "client/custom/src/views/quote/fields/item-list.js"
fetch "client/custom/src/views/modals/select-product-for-quote.js"

# Verifica metadata app (script registrato)
if ! grep -q 'custom-product-button' "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/app/client.json" 2>/dev/null; then
  echo "ATTENZIONE: registrare in custom/Espo/Custom/Resources/metadata/app/client.json:"
  echo '  "client/custom/src/custom-product-button.js" in scriptList'
fi

php command.php rebuild
rm -rf data/cache/*
echo ""
echo "Fatto. Ricaricare contratto con Ctrl+Shift+R (svuota cache browser)."
