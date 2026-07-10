#!/usr/bin/env bash
# Fix minus/plus: costi aggiuntivi (tasso zero €250 net, accessori Ariel legacy).
# Richiede PR provvigioni-regole-ariel già deployato.
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

echo "=== Minus/plus: costi aggiuntivi (tasso zero + accessori Ariel) ==="
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
  custom/Espo/Custom/Resources/i18n/it_IT/Quote.json
  custom/Espo/Custom/Resources/layouts/Quote/detail.json
  tools/migrate-ricalcola-provvigioni-contratti.php
)

for rel in "${FILES[@]}"; do
  fetch "${rel}"
done

php clear_cache.php
php rebuild.php

echo ""
echo "=== Ricalcolo provvigioni (minus/plus aggiornato) ==="
php tools/migrate-ricalcola-provvigioni-contratti.php --verbose 2>&1 | tail -30

echo ""
echo "=== Verifica singolo contratto (es. FILIPPETTI) ==="
echo "  php tools/migrate-ricalcola-provvigioni-contratti.php --codice=Contratto_00101 --verbose"
echo "=== Fine deploy costi aggiuntivi ==="
