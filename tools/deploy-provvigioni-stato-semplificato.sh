#!/usr/bin/env bash
# Deploy completo: layout contratto corretto + stati provvigioni semplificati.
#
# Ripristina:
#   - NO pannello «Provvigioni (calcolo)»
#   - Finanziamento con tutti i campi (importo finanziato, rate, saldo, tasso zero)
#   - Importo caparra in Panoramica
#   - Articoli + totali a destra
#
# Uso:
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/provvigioni-stato-semplificato-9999/tools/deploy-provvigioni-stato-semplificato.sh?t=$(date +%s)" | bash
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="cursor/provvigioni-stato-semplificato-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"
TS="$(date +%s)"

cd "${CRM_ROOT}" || exit 1

echo "=== Deploy layout contratto + stati provvigioni ==="

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
  custom/Espo/Custom/Resources/layouts/Quote/relationships/provvigioni.json
  custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json
  custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json
  custom/Espo/Custom/Resources/metadata/entityDefs/Provvigione.json
  custom/Espo/Custom/Resources/metadata/selectDefs/Provvigione.json
  custom/Espo/Custom/Resources/metadata/clientDefs/Provvigione.json
  custom/Espo/Custom/Resources/i18n/it_IT/Quote.json
  custom/Espo/Custom/Resources/i18n/it_IT/Provvigione.json
  custom/Espo/Custom/Services/ProvvigioneStatusSync.php
  custom/Espo/Custom/Services/ProvvigioneManager.php
  custom/Espo/Custom/Services/InvitoAFatturareManager.php
  custom/Espo/Custom/Hooks/Quote/SyncProvvigioniStato.php
  custom/Espo/Custom/Hooks/InvitoAFatturare/BeforeSave.php
  custom/Espo/Custom/Actions/Opportunity/CreateContratto.php
  custom/Espo/Custom/Classes/Select/Provvigione/PrimaryFilters/Forecast.php
  custom/Espo/Custom/Classes/Select/Provvigione/PrimaryFilters/InPagamento.php
  custom/Espo/Custom/Classes/Select/Provvigione/PrimaryFilters/Pagato.php
  custom/Espo/Custom/Classes/Select/Provvigione/PrimaryFilters/Inesigibile.php
  client/custom/src/views/quote/record/detail.js
  client/custom/src/views/quote/record/panels/items.js
  client/custom/src/views/quote/record/panels/finanziamento.js
  client/custom/src/handlers/quote/crea-prodotto-articoli.js
  client/custom/src/handlers/quote/ricalcola-provvigioni.js
  tools/migrate-provvigioni-stato-semplificato.php
)

for rel in "${FILES[@]}"; do
  fetch "${rel}"
done

php clear_cache.php
php rebuild.php

echo ""
echo "=== Migrazione stati provvigioni ==="
php tools/migrate-provvigioni-stato-semplificato.php

php clear_cache.php

echo ""
echo "=== Fatto ==="
echo "  - Pannello Provvigioni (calcolo) RIMOSSO"
echo "  - Importo caparra in Panoramica"
echo "  - Finanziamento con tutti i campi"
echo "  - Stati provvigioni: Forecast | In pagamento | Pagato | Inesigibile"
