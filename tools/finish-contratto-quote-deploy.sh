#!/usr/bin/env bash
# Completa deploy contratto: client views (obbligatorie) + backfill + verifica.
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/fix-contratto-quote-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}" || exit 1

CLIENT_FILES=(
  client/custom/src/handlers/quote/catalog-prices.js
  client/custom/src/handlers/quote/crea-prodotto-articoli.js
  client/custom/src/handlers/quote/ricalcola-provvigioni.js
  client/custom/src/views/quote/record/detail.js
  client/custom/src/views/quote/record/item.js
  client/custom/src/views/quote/fields/item-list.js
  client/custom/src/views/quote/record/panels/items.js
  client/custom/src/views/quote/fields/item-list.js
  client/custom/src/views/provvigione/record/detail.js
  client/custom/src/views/provvigione/record/edit.js
)

fetch() {
  local rel="$1"
  mkdir -p "$(dirname "${rel}")"
  curl -fsSL "${BASE}/${rel}?t=$(date +%s)" -o "${rel}"
  echo "OK ${rel}"
}

echo "=== 1) Client views Quote/Provvigione (obbligatorie) ==="
for rel in "${CLIENT_FILES[@]}"; do
  fetch "${rel}"
done

if [[ ! -f client/custom/src/views/quote/record/detail.js ]]; then
  echo "ERRORE: detail.js non scaricato — CRM non si apre senza questo file"
  exit 1
fi

echo ""
echo "=== 2) Pricing B2C + QuoteItem + Finanziamento + Provvigioni ==="
curl -fsSL "${BASE}/custom/Espo/Custom/Services/QuotePricingCalculator.php?t=$(date +%s)" \
  -o custom/Espo/Custom/Services/QuotePricingCalculator.php
curl -fsSL "${BASE}/custom/Espo/Custom/Services/QuoteProvvigioniSync.php?t=$(date +%s)" \
  -o custom/Espo/Custom/Services/QuoteProvvigioniSync.php
curl -fsSL "${BASE}/custom/Espo/Custom/Hooks/Quote/SyncContractPricing.php?t=$(date +%s)" \
  -o custom/Espo/Custom/Hooks/Quote/SyncContractPricing.php
curl -fsSL "${BASE}/custom/Espo/Custom/Hooks/Quote/SyncFinanziamentoFromOpportunity.php?t=$(date +%s)" \
  -o custom/Espo/Custom/Hooks/Quote/SyncFinanziamentoFromOpportunity.php
curl -fsSL "${BASE}/custom/Espo/Custom/Hooks/Quote/BeforeSave.php?t=$(date +%s)" \
  -o custom/Espo/Custom/Hooks/Quote/BeforeSave.php
curl -fsSL "${BASE}/custom/Espo/Custom/Hooks/Quote/NormalizeDefaults.php?t=$(date +%s)" \
  -o custom/Espo/Custom/Hooks/Quote/NormalizeDefaults.php
curl -fsSL "${BASE}/custom/Espo/Custom/Services/ProvvigioneManager.php?t=$(date +%s)" \
  -o custom/Espo/Custom/Services/ProvvigioneManager.php
curl -fsSL "${BASE}/custom/Espo/Custom/Resources/metadata/formula/Quote.json?t=$(date +%s)" \
  -o custom/Espo/Custom/Resources/metadata/formula/Quote.json
curl -fsSL "${BASE}/custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json?t=$(date +%s)" \
  -o custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json
curl -fsSL "${BASE}/custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json?t=$(date +%s)" \
  -o custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json
curl -fsSL "${BASE}/custom/Espo/Custom/Resources/metadata/entityDefs/QuoteItem.json?t=$(date +%s)" \
  -o custom/Espo/Custom/Resources/metadata/entityDefs/QuoteItem.json
curl -fsSL "${BASE}/custom/Espo/Custom/Resources/layouts/QuoteItem/listItem.json?t=$(date +%s)" \
  -o custom/Espo/Custom/Resources/layouts/QuoteItem/listItem.json
curl -fsSL "${BASE}/custom/Espo/Custom/Tools/Quote/Api/PostGetItemCatalogPrices.php?t=$(date +%s)" \
  -o custom/Espo/Custom/Tools/Quote/Api/PostGetItemCatalogPrices.php
curl -fsSL "${BASE}/tools/migra-quote-finanziamento-da-opportunita.php?t=$(date +%s)" -o tools/migra-quote-finanziamento-da-opportunita.php
curl -fsSL "${BASE}/tools/backfill-quote-provvigioni.php?t=$(date +%s)" -o tools/backfill-quote-provvigioni.php
curl -fsSL "${BASE}/custom/Espo/Custom/Hooks/Quote/ProvvigioneConsolidata.php?t=$(date +%s)" \
  -o custom/Espo/Custom/Hooks/Quote/ProvvigioneConsolidata.php
php rebuild.php
php tools/migra-quote-finanziamento-da-opportunita.php
php tools/backfill-quote-provvigioni.php

echo ""
echo "=== 3) Backfill totaleProvvigioni ==="
curl -fsSL "${BASE}/tools/backfill-quote-totale-provvigioni.php?t=$(date +%s)" -o tools/backfill-quote-totale-provvigioni.php
php tools/backfill-quote-totale-provvigioni.php

echo ""
echo "=== 3) Cache ==="
php clear_cache.php
rm -rf data/cache/* 2>/dev/null || true

echo ""
echo "=== 4) Verifica ==="
curl -fsSL "${BASE}/tools/verify-contratto-quote-deploy.php?t=$(date +%s)" -o tools/verify-contratto-quote-deploy.php
php tools/verify-contratto-quote-deploy.php || {
  echo "ERRORE: verifica fallita"
  exit 1
}

echo ""
echo "Fatto. Ctrl+Shift+R nel browser."
