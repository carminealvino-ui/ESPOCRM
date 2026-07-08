#!/usr/bin/env bash
# Hotfix Crea Contratto + articoli Quote (getItemCatalogPrices).
#
# Uso:
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-calendario-appuntamento-9999/tools/deploy-create-contratto-fix.sh" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/fix-calendario-appuntamento-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"
TS="$(date +%s)"

cd "${CRM_ROOT}"

mkdir -p backup/custom/Espo/Custom/Controllers
if [[ -f custom/Espo/Custom/Controllers/Opportunity.php ]]; then
  cp custom/Espo/Custom/Controllers/Opportunity.php "backup/custom/Espo/Custom/Controllers/Opportunity.php.${TS}.bak"
fi

fetch() {
  local path="$1"
  mkdir -p "$(dirname "$path")"
  curl -fL --retry 8 --retry-all-errors --retry-delay 2 --retry-max-time 120 \
    "${BASE}/${path}" -o "${path}"
  echo "OK ${path}"
}

# Backend createContratto
fetch custom/Espo/Custom/Controllers/Opportunity.php
fetch custom/Espo/Custom/Actions/Opportunity/CreateContratto.php
fetch custom/Espo/Custom/Services/ReferenteContactService.php
fetch custom/Espo/Custom/Services/LeadProspectSync.php

# Backend prezzi articoli contratto (POST Quote/getItemCatalogPrices)
fetch custom/Espo/Custom/Resources/routes.json
fetch custom/Espo/Custom/Tools/Quote/Api/PostGetItemCatalogPrices.php
fetch custom/Espo/Custom/Services/QuotePricingCalculator.php
fetch custom/Espo/Custom/Services/QuoteProvvigioniSync.php

# Metadata Quote
fetch custom/Espo/Custom/Resources/metadata/app/client.json
fetch custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json
fetch custom/Espo/Custom/Resources/metadata/formula/Quote.json

# Frontend articoli contratto
fetch client/custom/src/handlers/quote/catalog-prices.js
fetch client/custom/src/views/quote/fields/item-list.js
fetch client/custom/src/views/quote/record/item.js
fetch custom/Espo/Custom/Resources/client/custom/src/handlers/quote/catalog-prices.js
fetch custom/Espo/Custom/Resources/client/custom/src/views/quote/fields/item-list.js
fetch custom/Espo/Custom/Resources/client/custom/src/views/quote/record/item.js

php clear_cache.php
php rebuild.php

echo "=== Fatto: deploy createContratto + articoli Quote completato ==="
