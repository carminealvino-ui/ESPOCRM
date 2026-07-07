#!/usr/bin/env bash
# Ripristina flusso Crea Opportunità da appuntamento Svolto + fix hook Opportunity Espo 10.
#
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-crea-opportunita-9999/tools/deploy-crea-opportunita-fix.sh" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/fix-crea-opportunita-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

echo "=== Deploy fix Crea Opportunità ==="
cd "${CRM_ROOT}"

fetch() {
  curl -fsSL "${BASE}/$1" -o "$1"
  echo "OK $1"
}

fetch client/custom/src/views/appuntamento/popup-notification.js
fetch client/custom/res/templates/appuntamento/popup-notification.tpl
fetch client/custom/src/views/opportunity/helpers/appuntamento-sync.js
fetch client/custom/src/views/opportunity/record/edit.js
fetch custom/Espo/Custom/Resources/metadata/app/popupNotifications.json
fetch custom/Espo/Custom/Resources/layouts/Appuntamento/detailEsitoPopup.json
fetch custom/Espo/Custom/Hooks/Opportunity/GlobalLogic.php

php clear_cache.php && php rebuild.php

echo "=== Completato — Ctrl+Shift+R, poi popup appuntamento Svolto → Crea Opportunità ==="
