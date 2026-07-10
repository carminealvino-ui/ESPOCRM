#!/usr/bin/env bash
# Ripristina metadata Provvigione (tipo, base, tasso, nome) + costi aggiuntivi minus/plus.
#
# Uso:
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-minusvalenze-costi-aggiuntivi-9999/tools/deploy-minusvalenze-costi-aggiuntivi.sh?t=$(date +%s)" | bash
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="cursor/fix-minusvalenze-costi-aggiuntivi-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"
TS="$(date +%s)"

cd "${CRM_ROOT}" || exit 1

echo "=== Ripristino provvigioni + costi aggiuntivi minus/plus ==="
echo "=== CRM: ${CRM_ROOT} ==="

fetch() {
  local path="$1"
  mkdir -p "$(dirname "$path")"
  curl -fL --retry 8 --retry-all-errors --retry-delay 2 --retry-max-time 120 \
    "${BASE}/${path}?t=${TS}" -o "${path}"
  echo "OK ${path}"
}

FILES=(
  custom/Espo/Custom/Services/QuotePricingCalculator.php
  custom/Espo/Custom/Services/ProvvigioneManager.php
  custom/Espo/Custom/Resources/metadata/entityDefs/Provvigione.json
  custom/Espo/Custom/Resources/metadata/clientDefs/Provvigione.json
  custom/Espo/Custom/Resources/metadata/scopes/Provvigione.json
  custom/Espo/Custom/Resources/metadata/selectDefs/Provvigione.json
  custom/Espo/Custom/Resources/i18n/it_IT/Quote.json
  custom/Espo/Custom/Resources/i18n/it_IT/Provvigione.json
  custom/Espo/Custom/Resources/layouts/Quote/detail.json
  custom/Espo/Custom/Resources/layouts/Provvigione/list.json
  custom/Espo/Custom/Resources/layouts/Provvigione/detail.json
  custom/Espo/Custom/Resources/layouts/Provvigione/edit.json
  custom/Espo/Custom/Resources/layouts/Provvigione/filters.json
  custom/Espo/Custom/Resources/layouts/Provvigione/massUpdate.json
  custom/Espo/Custom/Resources/layouts/Quote/relationships/provvigioni.json
  custom/Espo/Custom/Classes/Select/Provvigione/PrimaryFilters/Consolidata.php
  custom/Espo/Custom/Classes/Select/Provvigione/PrimaryFilters/Fatturata.php
  custom/Espo/Custom/Classes/Select/Provvigione/PrimaryFilters/InInvito.php
  custom/Espo/Custom/Classes/Select/Provvigione/PrimaryFilters/Prevista.php
  custom/Espo/Custom/Classes/Select/Provvigione/PrimaryFilters/Stornata.php
  client/custom/src/views/provvigione/record/detail.js
  client/custom/src/views/provvigione/record/edit.js
  custom/Espo/Custom/Resources/client/custom/src/views/provvigione/record/detail.js
  custom/Espo/Custom/Resources/client/custom/src/views/provvigione/record/edit.js
  tools/migrate-ricalcola-provvigioni-contratti.php
)

for rel in "${FILES[@]}"; do
  fetch "${rel}"
done

php clear_cache.php
php rebuild.php

echo ""
echo "=== Ricalcolo provvigioni (ripopola nome, tipo, base, tasso) ==="
php tools/migrate-ricalcola-provvigioni-contratti.php --verbose 2>&1 | tail -40

echo ""
echo "=== Verifica singolo contratto ==="
echo "  php tools/migrate-ricalcola-provvigioni-contratti.php --codice=Contratto_00101 --verbose"
echo "=== Fine deploy ==="
