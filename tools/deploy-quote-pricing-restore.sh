#!/usr/bin/env bash
# Ripristina stack prezzi contratto (IVA inclusa) sovrascritto dal deploy Espo 10.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-espocrm-10-compat-9999/tools/deploy-quote-pricing-restore.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/fix-espocrm-10-compat-9999"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)

FILES=(
  "custom/Espo/Custom/Hooks/Quote/BeforeSave.php"
  "custom/Espo/Custom/Hooks/Quote/SyncContractPricing.php"
  "custom/Espo/Custom/Services/QuotePricingCalculator.php"
  "custom/Espo/Custom/Services/ProvvigioneManager.php"
  "custom/Espo/Custom/Actions/Opportunity/CreateContratto.php"
  "custom/Espo/Custom/Hooks/Quote/ProvvigioneConsolidata.php"
  "custom/Espo/Custom/Resources/i18n/it_IT/Quote.json"
  "custom/Espo/Custom/Tools/Quote/Api/PostGetItemCatalogPrices.php"
  "custom/Espo/Custom/Resources/client/custom/src/handlers/quote/catalog-prices.js"
  "custom/Espo/Custom/Resources/client/custom/src/views/quote/fields/item-list.js"
  "custom/Espo/Custom/Resources/client/custom/src/views/quote/record/item.js"
  "custom/Espo/Custom/Resources/client/custom/src/views/quote/record/detail.js"
  "custom/Espo/Custom/Resources/client/custom/src/views/quote/record/panels/items.js"
  "custom/Espo/Custom/Resources/client/custom/src/views/modals/select-product-for-quote.js"
  "custom/Espo/Custom/Resources/metadata/formula/Quote.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/QuoteItem.json"
  "custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json"
  "custom/Espo/Custom/Resources/metadata/clientDefs/QuoteItem.json"
  "custom/Espo/Custom/Resources/layouts/Quote/detail.json"
  "custom/Espo/Custom/Resources/layouts/QuoteItem/listItem.json"
)

echo "==> Ripristino prezzi contratto (${BRANCH})"

for rel in "${FILES[@]}"; do
  dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=${STAMP}" -o "${dest}"
  echo "  OK ${rel}"
done

cd "${CRM_ROOT}"
php command.php rebuild
php command.php clear-cache

echo "==> Completato"
