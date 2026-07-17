#!/usr/bin/env bash
# Ripristina produzione allo stato stabile PRIMA del deploy fix-totale-provvigioni-sync.
#
# Usa il branch cursor/provvigioni-stato-semplificato-9999 (layout + stati provvigioni
# funzionanti, senza pannello calcolo) e rimuove i file introdotti dal deploy rotto.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-totale-provvigioni-sync-9999/tools/rollback-to-stabile-pre-sync.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
STABLE_BRANCH="cursor/provvigioni-stato-semplificato-9999"
BASE_BRANCH="cursor/opportunity-globallogic-9999"
REPO="carminealvino-ui/ESPOCRM"
STABLE_BASE="https://raw.githubusercontent.com/${REPO}/${STABLE_BRANCH}"
LEGACY_BASE="https://raw.githubusercontent.com/${REPO}/${BASE_BRANCH}"
TS="$(date +%s)"

cd "${CRM_ROOT}" || exit 1

echo "=== ROLLBACK allo stato stabile pre fix-totale-provvigioni-sync ==="
echo "Branch stabile: ${STABLE_BRANCH}"
echo ""

fetch_stable() {
  local path="$1"
  mkdir -p "$(dirname "${path}")"
  curl -fsSL "${STABLE_BASE}/${path}?t=${TS}" -o "${path}"
  echo "OK ${path}"
}

fetch_legacy() {
  local path="$1"
  mkdir -p "$(dirname "${path}")"
  curl -fsSL "${LEGACY_BASE}/${path}?t=${TS}" -o "${path}"
  echo "OK (legacy) ${path}"
}

STABLE_FILES=(
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
  custom/Espo/Custom/Resources/metadata/app/actions.json
  custom/Espo/Custom/Resources/i18n/it_IT/Quote.json
  custom/Espo/Custom/Resources/i18n/it_IT/Provvigione.json
  custom/Espo/Custom/Services/ProvvigioneStatusSync.php
  custom/Espo/Custom/Services/ProvvigioneManager.php
  custom/Espo/Custom/Services/InvitoAFatturareManager.php
  custom/Espo/Custom/Hooks/Quote/AfterSaveTotaleProvvigioni.php
  custom/Espo/Custom/Hooks/Quote/ProvvigioneConsolidata.php
  custom/Espo/Custom/Hooks/Quote/SetPresentedWhenNumeroContratto.php
  custom/Espo/Custom/Hooks/Quote/SyncContractPricing.php
  custom/Espo/Custom/Hooks/Quote/SyncProvvigioniStato.php
  custom/Espo/Custom/Hooks/Provvigione/AccrualAndAmount.php
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
)

for rel in "${STABLE_FILES[@]}"; do
  fetch_stable "${rel}"
done

# Hook legacy pre-sync (BeforeSave con afterSave provvigioni + BeforeSaveLegacy)
fetch_legacy custom/Espo/Custom/Hooks/Quote/BeforeSave.php
fetch_legacy custom/Espo/Custom/Hooks/Provvigione/BeforeSaveLegacy.php

REMOVE_FILES=(
  custom/Espo/Custom/Hooks/Quote/BeforeSaveTotaleProvvigioni.php
  custom/Espo/Custom/Hooks/Quote/NormalizeStati.php
  custom/Espo/Custom/Hooks/Provvigione/AfterSaveRefreshQuoteTotale.php
  custom/Espo/Custom/Actions/Quote/RicalcolaProvvigioni.php
  custom/Espo/Custom/Resources/metadata/logicDefs/Quote.json
  custom/Espo/Custom/Resources/metadata/logicDefs/Provvigione.json
  custom/Espo/Custom/Resources/metadata/formula/Provvigione.json
)

echo ""
echo "=== Rimozione file introdotti dal deploy rotto ==="
for rel in "${REMOVE_FILES[@]}"; do
  if [[ -f "${rel}" ]]; then
    rm -f "${rel}"
    echo "RM ${rel}"
  fi
done

php clear_cache.php
php rebuild.php
php clear_cache.php

if grep -q 'Provvigioni (calcolo)' custom/Espo/Custom/Resources/layouts/Quote/detail.json 2>/dev/null; then
  echo ""
  echo "ATTENZIONE: detail.json contiene ancora 'Provvigioni (calcolo)'"
  exit 1
fi

echo ""
echo "=== ROLLBACK COMPLETATO ==="
echo "  - Layout contratto stabile (no pannello calcolo)"
echo "  - Hook e servizi provvigioni da ${STABLE_BRANCH}"
echo "  - BeforeSave legacy ripristinato"
echo ""
echo "Ctrl+Shift+R nel browser."
echo ""
echo "NOTA: la migrazione stati ha modificato Contratto_00138 e Contratto_00143"
echo "(statoContratto -> Chiuso). Se serve, correggili manualmente in CRM."
