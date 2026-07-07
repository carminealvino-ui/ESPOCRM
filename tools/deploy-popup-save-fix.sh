#!/usr/bin/env bash
# Fix salvataggio popup Contatto Telefonico (sync stato da form).
#
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-kpi-lordi-pianificati-9999/tools/deploy-popup-save-fix.sh" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/fix-kpi-lordi-pianificati-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}"

fetch() {
  mkdir -p "$(dirname "$1")"
  curl -fsSL "${BASE}/$1" -o "$1"
  echo "OK $1"
}

fetch client/custom/src/views/appuntamento/popup-notification.js
fetch client/custom/src/helpers/call-esito-popup-defaults.js
fetch custom/Espo/Custom/Services/CallStandardTesto.php
fetch custom/Espo/Custom/Controllers/CallStandardTesto.php
fetch custom/Espo/Custom/Hooks/Call/PersistStandardTesto.php

php clear_cache.php && php rebuild.php

echo "=== Fatto — Ctrl+Shift+R e riprova Salva nel popup ==="
