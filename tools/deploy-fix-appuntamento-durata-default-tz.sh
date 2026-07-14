#!/usr/bin/env bash
# Fix durata default appuntamento da calendario (1h30 → Date End +2h = 3h30).
# Scrive in client/custom/src (path effettivamente caricato dal browser).
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

# Path LIVE (caricati dal browser) + copie in custom/Espo per sync repo
FILES=(
  custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php
  custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json
  custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json
  custom/Espo/Custom/Resources/metadata/clientDefs/Calendar.json

  client/custom/src/helpers/appuntamento-duration.js
  client/custom/src/views/fields/appuntamento-duration.js
  client/custom/src/views/appuntamento/record/edit-small.js
  client/custom/src/views/appuntamento/record/edit.js
  client/custom/src/views/appuntamento/modals/detail.js
  client/custom/src/views/calendar/calendar.js
  client/custom/src/views/calendar/modals/edit.js

  custom/Espo/Custom/client/custom/src/helpers/appuntamento-duration.js
  custom/Espo/Custom/client/custom/src/views/fields/appuntamento-duration.js
  custom/Espo/Custom/client/custom/src/views/appuntamento/record/edit-small.js
  custom/Espo/Custom/client/custom/src/views/appuntamento/record/edit.js
  custom/Espo/Custom/client/custom/src/views/appuntamento/modals/detail.js
  custom/Espo/Custom/client/custom/src/views/calendar/calendar.js
  custom/Espo/Custom/client/custom/src/views/calendar/modals/edit.js

  custom/Espo/Custom/Resources/client/custom/src/helpers/appuntamento-duration.js
  custom/Espo/Custom/Resources/client/custom/src/views/fields/appuntamento-duration.js
  custom/Espo/Custom/Resources/client/custom/src/views/appuntamento/record/edit-small.js
  custom/Espo/Custom/Resources/client/custom/src/views/appuntamento/record/edit.js
  custom/Espo/Custom/Resources/client/custom/src/views/appuntamento/modals/detail.js
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
echo "=== Verifica file LIVE (client/custom) ==="
if grep -q 'moment.utc' client/custom/src/helpers/appuntamento-duration.js \
  && grep -q 'appuntamento-duration' custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json \
  && grep -q 'custom:helpers/appuntamento-duration' client/custom/src/views/appuntamento/record/edit-small.js; then
  echo "OK: helper UTC + edit-small aggiornati in client/custom"
else
  echo "ERRORE: file client/custom non aggiornati correttamente" >&2
  exit 1
fi

# Controlla che NON resti il vecchio toMoment+.format senza helper
if grep -n "toMoment(dateStart)" client/custom/src/views/appuntamento/record/edit-small.js | grep -v helpers; then
  echo "ATTENZIONE: edit-small potrebbe ancora usare toMoment" >&2
fi

echo ""
echo "Fatto. OBBLIGATORIO hard refresh: Ctrl+Shift+R (o finestra anonima)."
echo "Test: crea da calendario alle 07:00 → Date End deve essere 08:30 (non 10:30)."
