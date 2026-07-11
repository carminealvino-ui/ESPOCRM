#!/usr/bin/env bash
# Ripristina layout contratto senza pannello «Provvigioni (calcolo)».
#
# Uso:
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-quote-rimuovi-pannello-provvigioni-9999/tools/deploy-quote-layout-no-pannello-provvigioni.sh?t=$(date +%s)" | bash
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="cursor/fix-quote-rimuovi-pannello-provvigioni-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"
TS="$(date +%s)"

cd "${CRM_ROOT}" || exit 1

echo "=== Ripristino layout Contratto (senza pannello Provvigioni calcolo) ==="

fetch() {
  local path="$1"
  mkdir -p "$(dirname "$path")"
  curl -fL --retry 8 --retry-all-errors --retry-delay 2 --retry-max-time 120 \
    "${BASE}/${path}?t=${TS}" -o "${path}"
  echo "OK ${path}"
}

FILES=(
  custom/Espo/Custom/Resources/layouts/Quote/detail.json
  custom/Espo/Custom/Resources/layouts/Quote/detailBottom.json
  custom/Espo/Custom/Resources/layouts/Quote/detailBottomTotal.json
  custom/Espo/Custom/Resources/layouts/Quote/finanziamento.json
  custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json
  custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json
  custom/Espo/Custom/Resources/i18n/it_IT/Quote.json
  custom/Espo/Custom/Services/QuotePricingCalculator.php
  custom/Espo/Custom/Services/QuoteProvvigioniSync.php
  custom/Espo/Custom/Hooks/Quote/SyncContractPricing.php
  custom/Espo/Custom/Hooks/Quote/ProvvigioneConsolidata.php
  client/custom/src/views/quote/record/detail.js
  client/custom/src/views/quote/record/item.js
  client/custom/src/views/quote/record/panels/items.js
  client/custom/src/views/quote/record/panels/finanziamento.js
  client/custom/src/views/quote/fields/item-list.js
  client/custom/src/handlers/quote/catalog-prices.js
  client/custom/src/handlers/quote/crea-prodotto-articoli.js
  client/custom/src/handlers/quote/ricalcola-provvigioni.js
)

for rel in "${FILES[@]}"; do
  fetch "${rel}"
done

php clear_cache.php
php rebuild.php
php clear_cache.php

echo ""
echo "=== Fine ==="
echo "  - Pannello «Provvigioni (calcolo)» rimosso"
echo "  - Costi aggiuntivi sotto Articoli (ex Spese di Installazione), non in Finanziamento"
echo "  - Prezzo Codice Totale include costi aggiuntivi / tasso zero"
