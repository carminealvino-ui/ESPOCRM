#!/usr/bin/env bash
# Referenza Personale: il 6% si aggiunge alla provvigione base (non la sostituisce).
#
# Uso:
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-referenza-personale-provvigione-base-9999/tools/deploy-referenza-personale-provvigione-base.sh?t=$(date +%s)" | bash
#
# Ricalcolo singolo contratto (es. BELLITO CLAUDIO):
#   php tools/migrate-ricalcola-provvigioni-contratti.php --codice=Contratto_XXXXX --verbose
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="cursor/fix-referenza-personale-provvigione-base-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"
TS="$(date +%s)"

cd "${CRM_ROOT}" || exit 1

echo "=== Fix referenza personale + provvigione base ==="
echo "=== CRM: ${CRM_ROOT} ==="

fetch() {
  local path="$1"
  mkdir -p "$(dirname "$path")"
  curl -fL --retry 8 --retry-all-errors --retry-delay 2 --retry-max-time 120 \
    "${BASE}/${path}?t=${TS}" -o "${path}"
  echo "OK ${path}"
}

fetch custom/Espo/Custom/Services/ProvvigioneManager.php

php clear_cache.php
php rebuild.php

echo ""
echo "=== Ricalcolo provvigioni su tutti i contratti ==="
php tools/migrate-ricalcola-provvigioni-contratti.php

php clear_cache.php

echo ""
echo "=== Fatto ==="
echo "Atteso per contratti con Referenza Personale: 3 righe"
echo "  - Provvigione Base (10+5% o 10%)"
echo "  - Referenza Personale (+6%)"
echo "  - Bonus weekend (se sabato/domenica)"
