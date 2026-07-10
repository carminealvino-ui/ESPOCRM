#!/usr/bin/env bash
# Stato provvigione: modificabile, filtri e aggiornamento massivo.
#
# Uso:
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/provvigione-stato-modificabile-9999/tools/deploy-provvigione-stato-modificabile.sh?t=$(date +%s)" | bash
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="cursor/provvigione-stato-modificabile-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"
TS="$(date +%s)"

cd "${CRM_ROOT}" || exit 1

echo "=== Provvigione: stato modificabile + filtri + mass update ==="

fetch() {
  local path="$1"
  mkdir -p "$(dirname "$path")"
  curl -fL --retry 8 --retry-all-errors --retry-delay 2 --retry-max-time 120 \
    "${BASE}/${path}?t=${TS}" -o "${path}"
  echo "OK ${path}"
}

FILES=(
  client/custom/src/views/provvigione/record/edit.js
  client/custom/src/views/provvigione/record/detail.js
  custom/Espo/Custom/Resources/client/custom/src/views/provvigione/record/edit.js
  custom/Espo/Custom/Resources/client/custom/src/views/provvigione/record/detail.js
  custom/Espo/Custom/Classes/Select/Provvigione/PrimaryFilters/Consolidata.php
  custom/Espo/Custom/Classes/Select/Provvigione/PrimaryFilters/Prevista.php
  custom/Espo/Custom/Classes/Select/Provvigione/PrimaryFilters/InInvito.php
  custom/Espo/Custom/Classes/Select/Provvigione/PrimaryFilters/Fatturata.php
  custom/Espo/Custom/Classes/Select/Provvigione/PrimaryFilters/Stornata.php
  custom/Espo/Custom/Resources/metadata/clientDefs/Provvigione.json
  custom/Espo/Custom/Resources/metadata/selectDefs/Provvigione.json
  custom/Espo/Custom/Resources/metadata/scopes/Provvigione.json
  custom/Espo/Custom/Resources/metadata/entityDefs/Provvigione.json
  custom/Espo/Custom/Resources/i18n/it_IT/Provvigione.json
  custom/Espo/Custom/Resources/layouts/Provvigione/detail.json
  custom/Espo/Custom/Resources/layouts/Provvigione/edit.json
  custom/Espo/Custom/Resources/layouts/Provvigione/list.json
  custom/Espo/Custom/Resources/layouts/Provvigione/filters.json
  custom/Espo/Custom/Resources/layouts/Provvigione/massUpdate.json
)

for rel in "${FILES[@]}"; do
  fetch "${rel}"
done

php clear_cache.php
php rebuild.php
php clear_cache.php

echo ""
echo "=== Fatto ==="
echo "- Stato provvigione modificabile in dettaglio/modifica"
echo "- Filtri rapidi: Consolidate, Previste, In invito, Fatturate, Stornate"
echo "- Aggiornamento massivo: Stato provvigione"
echo "Ctrl+Shift+R sulla lista Provvigioni"
