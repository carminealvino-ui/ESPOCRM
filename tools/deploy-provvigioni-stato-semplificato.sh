#!/usr/bin/env bash
# Stati provvigioni semplificati: Forecast / In pagamento / Pagato / Inesigibile
#
# Uso:
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/provvigioni-stato-semplificato-9999/tools/deploy-provvigioni-stato-semplificato.sh?t=$(date +%s)" | bash
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="cursor/provvigioni-stato-semplificato-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"
TS="$(date +%s)"

cd "${CRM_ROOT}" || exit 1

echo "=== Deploy stati provvigioni semplificati ==="

fetch() {
  local path="$1"
  mkdir -p "$(dirname "$path")"
  curl -fL --retry 8 --retry-all-errors --retry-delay 2 --retry-max-time 120 \
    "${BASE}/${path}?t=${TS}" -o "${path}"
  echo "OK ${path}"
}

FILES=(
  custom/Espo/Custom/Services/ProvvigioneStatusSync.php
  custom/Espo/Custom/Services/ProvvigioneManager.php
  custom/Espo/Custom/Services/InvitoAFatturareManager.php
  custom/Espo/Custom/Hooks/Opportunity/SyncProvvigioniStato.php
  custom/Espo/Custom/Hooks/InvitoAFatturare/BeforeSave.php
  custom/Espo/Custom/Classes/Select/Provvigione/PrimaryFilters/Forecast.php
  custom/Espo/Custom/Classes/Select/Provvigione/PrimaryFilters/InPagamento.php
  custom/Espo/Custom/Classes/Select/Provvigione/PrimaryFilters/Pagato.php
  custom/Espo/Custom/Classes/Select/Provvigione/PrimaryFilters/Inesigibile.php
  custom/Espo/Custom/Resources/metadata/entityDefs/Provvigione.json
  custom/Espo/Custom/Resources/metadata/entityDefs/Opportunity.json
  custom/Espo/Custom/Resources/metadata/selectDefs/Provvigione.json
  custom/Espo/Custom/Resources/metadata/clientDefs/Provvigione.json
  custom/Espo/Custom/Resources/i18n/it_IT/Provvigione.json
  custom/Espo/Custom/Resources/i18n/it_IT/Opportunity.json
  custom/Espo/Custom/Resources/layouts/Quote/relationships/provvigioni.json
  tools/migrate-provvigioni-stato-semplificato.php
)

for rel in "${FILES[@]}"; do
  fetch "${rel}"
done

php clear_cache.php
php rebuild.php

echo ""
echo "=== Migrazione stati legacy ==="
php tools/migrate-provvigioni-stato-semplificato.php

php clear_cache.php

echo ""
echo "=== Fatto ==="
echo "Stati: Forecast | In pagamento | Pagato | Inesigibile"
echo "Pagamento: giorno 15 del mese successivo a installazione o caparra > 15%"
