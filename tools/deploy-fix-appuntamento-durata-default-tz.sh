#!/usr/bin/env bash
# Fix durata default appuntamento da calendario (1h30 → mostrava 3h30 per timezone).
#
# Uso:
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-appuntamento-durata-default-tz-9999/tools/deploy-fix-appuntamento-durata-default-tz.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/fix-appuntamento-durata-default-tz-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"
TS="$(date +%s)"

if [[ ! -d "${CRM_ROOT}" ]]; then
  echo "ERRORE: cartella CRM non trovata: ${CRM_ROOT}" >&2
  exit 1
fi

cd "${CRM_ROOT}"

PHP_BIN="${PHP_BIN:-php}"

fetch() {
  local path="$1"
  mkdir -p "$(dirname "${path}")"
  curl -fL --retry 8 --retry-all-errors --retry-delay 2 --retry-max-time 120 \
    "${BASE}/${path}?t=${TS}" -o "${path}"
  echo "OK ${path}"
}

FILES=(
  custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php
  custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json
  custom/Espo/Custom/Resources/metadata/clientDefs/Calendar.json
  custom/Espo/Custom/client/custom/src/views/appuntamento/record/edit-small.js
  custom/Espo/Custom/client/custom/src/views/appuntamento/record/edit.js
  custom/Espo/Custom/client/custom/src/views/calendar/calendar.js
  custom/Espo/Custom/client/custom/src/views/calendar/modals/edit.js
  custom/Espo/Custom/Resources/client/custom/src/views/appuntamento/record/edit-small.js
  custom/Espo/Custom/Resources/client/custom/src/views/appuntamento/record/edit.js
  custom/Espo/Custom/Resources/client/custom/src/views/calendar/calendar.js
  custom/Espo/Custom/Resources/client/custom/src/views/calendar/modals/edit.js
)

for rel in "${FILES[@]}"; do
  fetch "${rel}"
done

"${PHP_BIN}" clear_cache.php
"${PHP_BIN}" rebuild.php
"${PHP_BIN}" clear_cache.php

echo ""
echo "Verifica fix timezone in edit-small:"
grep -n 'utc()' custom/Espo/Custom/client/custom/src/views/appuntamento/record/edit-small.js || true
grep -n 'utc()' custom/Espo/Custom/client/custom/src/views/calendar/calendar.js || true

echo ""
echo "Fatto. Hard-refresh browser (Ctrl+Shift+R), poi crea un appuntamento dal calendario:"
echo "  Data 15:30 + durata 1h30 → Date End deve essere 17:00 (non 19:00)."
