#!/usr/bin/env bash
# Fix creazione appuntamenti da calendario (ProspectSync.computeDateEnd).
#
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-calendario-appuntamento-9999/tools/deploy-calendario-appuntamento-fix.sh" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/fix-calendario-appuntamento-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}"

fetch() {
  mkdir -p "$(dirname "$1")"
  curl -fsSL "${BASE}/$1" -o "$1"
  echo "OK $1"
}

fetch client/custom/src/helpers/appuntamento-prospect-sync.js
fetch client/custom/src/views/appuntamento/fields/duration.js
fetch client/custom/src/views/appuntamento/record/edit.js
fetch client/custom/src/views/appuntamento/record/edit-small.js
fetch client/custom/src/views/calendar/calendar.js
fetch client/custom/src/views/calendar/modals/edit.js
fetch custom/Espo/Custom/Resources/metadata/clientDefs/Calendar.json
fetch custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json
fetch custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json

php clear_cache.php && php rebuild.php

echo "=== Fatto — Ctrl+Shift+R e riprova creazione appuntamento da calendario ==="
