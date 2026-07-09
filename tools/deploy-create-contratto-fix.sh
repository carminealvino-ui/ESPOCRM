#!/usr/bin/env bash
# Hotfix Crea Contratto + articoli Quote (getItemCatalogPrices).
#
# Uso:
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-calendario-appuntamento-9999/tools/deploy-create-contratto-fix.sh" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/fix-contratto-stato-provvigioni-9999"
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
fetch custom/Espo/Custom/Hooks/Quote/SyncContractPricing.php
fetch custom/Espo/Custom/Hooks/Provvigione/AccrualAndAmount.php
fetch custom/Espo/Custom/Hooks/Quote/AfterSaveTotaleProvvigioni.php
fetch custom/Espo/Custom/Hooks/Quote/SetPresentedWhenNumeroContratto.php
fetch custom/Espo/Custom/Hooks/Quote/ProvvigioneConsolidata.php
fetch custom/Espo/Custom/Services/ProvvigioneManager.php

# Rimuove hook legacy incompatibile Espo 10 (Espo\Core\Hooks\Base).
rm -f custom/Espo/Custom/Hooks/Quote/BeforeSave.php

# Metadata Quote (NON toccare client.json / CSS KPI — usare deploy-restore-kpi-layout.sh)
fetch custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json
fetch custom/Espo/Custom/Resources/metadata/formula/Quote.json
fetch custom/Espo/Custom/Resources/metadata/formula/QuoteItem.json

# Frontend articoli contratto
fetch client/custom/src/handlers/quote/catalog-prices.js
fetch client/custom/src/views/quote/fields/item-list.js
fetch client/custom/src/views/quote/record/item.js
fetch custom/Espo/Custom/Resources/client/custom/src/handlers/quote/catalog-prices.js
fetch custom/Espo/Custom/Resources/client/custom/src/views/quote/fields/item-list.js
fetch custom/Espo/Custom/Resources/client/custom/src/views/quote/record/item.js
fetch tools/migrate-ricalcola-provvigioni-contratti.php

php clear_cache.php
php rebuild.php

if grep -qE '(^|[^a-zA-Z_])empty\(' custom/Espo/Custom/Resources/metadata/formula/Quote.json custom/Espo/Custom/Resources/metadata/formula/QuoteItem.json 2>/dev/null; then
  echo "ERRORE: formula Quote/QuoteItem contiene chiamata empty() non supportata da Espo" >&2
  exit 1
fi

echo "=== Fatto: deploy createContratto + articoli Quote completato ==="
echo ""
echo "NOTA: questo script NON modifica client.json né CSS KPI."
echo "Per ripristinare il layout KPI:"
echo "  curl -fsSL \"https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-contratto-stato-provvigioni-9999/tools/deploy-restore-kpi-layout.sh\" | bash"
echo ""
echo "Per aggiornare TUTTI i contratti (provvigioni + stato):"
echo "  php tools/migrate-ricalcola-provvigioni-contratti.php"
echo "  php tools/migrate-ricalcola-provvigioni-contratti.php --dry-run"
