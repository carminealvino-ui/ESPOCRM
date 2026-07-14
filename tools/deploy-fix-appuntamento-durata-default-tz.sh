#!/usr/bin/env bash
# Fix durata default appuntamento da calendario.
# Path LIVE: client/custom/src (è quello caricato dal browser).
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
  custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json
  custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json
  custom/Espo/Custom/Resources/metadata/clientDefs/Calendar.json

  client/custom/src/views/appuntamento/record/edit-small.js
  client/custom/src/views/appuntamento/record/edit.js
  client/custom/src/views/appuntamento/modals/detail.js
  client/custom/src/views/fields/appuntamento-duration.js
  client/custom/src/views/calendar/calendar.js
  client/custom/src/views/calendar/modals/edit.js

  custom/Espo/Custom/client/custom/src/views/appuntamento/record/edit-small.js
  custom/Espo/Custom/client/custom/src/views/appuntamento/record/edit.js
  custom/Espo/Custom/client/custom/src/views/appuntamento/modals/detail.js
  custom/Espo/Custom/client/custom/src/views/fields/appuntamento-duration.js
  custom/Espo/Custom/client/custom/src/views/calendar/calendar.js
  custom/Espo/Custom/client/custom/src/views/calendar/modals/edit.js

  custom/Espo/Custom/Resources/client/custom/src/views/appuntamento/record/edit-small.js
  custom/Espo/Custom/Resources/client/custom/src/views/appuntamento/record/edit.js
  custom/Espo/Custom/Resources/client/custom/src/views/appuntamento/modals/detail.js
  custom/Espo/Custom/Resources/client/custom/src/views/fields/appuntamento-duration.js
  custom/Espo/Custom/Resources/client/custom/src/views/calendar/calendar.js
  custom/Espo/Custom/Resources/client/custom/src/views/calendar/modals/edit.js
)

for rel in "${FILES[@]}"; do
  fetch "${rel}"
done

rm -rf data/cache/* 2>/dev/null || true
"${PHP_BIN}" clear_cache.php
"${PHP_BIN}" rebuild.php
"${PHP_BIN}" clear_cache.php

echo ""
echo "=== Verifica LIVE client/custom ==="
ok=1
grep -q 'moment.utc' client/custom/src/views/appuntamento/record/edit-small.js || ok=0
grep -q 'moment.utc' client/custom/src/views/fields/appuntamento-duration.js || ok=0
grep -q 'custom:views/fields/appuntamento-duration' custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json || ok=0
# Non deve più dipendere da helper AMD
if grep -q 'custom:helpers/appuntamento-duration' client/custom/src/views/appuntamento/record/edit-small.js; then
  echo "ERRORE: edit-small dipende ancora dall'helper AMD" >&2
  ok=0
fi

if [[ "${ok}" -eq 1 ]]; then
  echo "OK: edit-small + duration con moment.utc inline (niente helper AMD)"
else
  echo "ERRORE verifica" >&2
  exit 1
fi

echo ""
echo "Fatto. Hard refresh OBBLIGATORIO: Ctrl+Shift+R oppure finestra anonima."
echo "Test: crea alle 11:30 → Date End 13:00 (non 12:00), Durata 1h 30m."
echo ""
echo "Se in console vedi errori rossi sulla view appuntamento, incolla i messaggi."
