#!/usr/bin/env bash
# Fix creazione appuntamenti da calendario (ProspectSync.computeDateEnd).
#
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-calendario-appuntamento-9999/tools/deploy-calendario-appuntamento-fix.sh" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/fix-calendario-appuntamento-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"
TS=$(date +%s)

cd "${CRM_ROOT}"

if [[ -f custom/Espo/Custom/Controllers/Appuntamento.php ]]; then
  mkdir -p backup
  cp custom/Espo/Custom/Controllers/Appuntamento.php "backup/Appuntamento.php.bak-${TS}"
  echo "Backup controller Appuntamento esistente"
fi

if [[ -f custom/Espo/Custom/Controllers/Opportunity.php ]]; then
  mkdir -p backup
  cp custom/Espo/Custom/Controllers/Opportunity.php "backup/Opportunity.php.bak-${TS}"
  echo "Backup controller Opportunity esistente"
fi

fetch() {
  mkdir -p "$(dirname "$1")"
  curl -fsSL "${BASE}/$1" -o "$1"
  echo "OK $1"
}

fetch client/custom/src/helpers/appuntamento-prospect-sync.js
fetch client/custom/src/helpers/appuntamento-sottostato-map.js
fetch client/custom/src/views/fields/appuntamento-sottostato.js
fetch client/custom/src/views/fields/appuntamento-sottostato-popup.js
fetch client/custom/src/views/fields/appuntamento-parent.js
fetch client/custom/src/views/appuntamento/popup-notification.js
fetch client/custom/res/templates/appuntamento/popup-notification.tpl
fetch client/custom/src/views/appuntamento/fields/duration.js
fetch client/custom/src/views/appuntamento/record/edit.js
fetch client/custom/src/views/appuntamento/record/edit-small.js
fetch client/custom/src/views/calendar/calendar.js
fetch client/custom/src/views/calendar/modals/edit.js
fetch custom/Espo/Custom/Controllers/Appuntamento.php
fetch custom/Espo/Custom/Controllers/Opportunity.php
fetch custom/Espo/Custom/Actions/Opportunity/CreateContratto.php
fetch custom/Espo/Custom/Services/ReferenteContactService.php
fetch custom/Espo/Custom/Services/LeadProspectSync.php
fetch custom/Espo/Custom/Resources/layouts/Appuntamento/detailEsitoPopup.json
fetch custom/Espo/Custom/Resources/metadata/app/popupNotifications.json
fetch custom/Espo/Custom/Resources/metadata/scopes/Appuntamento.json
fetch custom/Espo/Custom/Resources/metadata/clientDefs/Calendar.json
fetch custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json
fetch custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json
fetch custom/Espo/Custom/Resources/metadata/logicDefs/Appuntamento.json
fetch custom/Espo/Custom/Resources/layouts/Opportunity/detailSmall.json
fetch custom/Espo/Custom/Resources/layouts/Opportunity/detail.json
fetch custom/Espo/Custom/Resources/metadata/entityDefs/Opportunity.json
fetch custom/Espo/Custom/Resources/metadata/logicDefs/Opportunity.json
fetch custom/Espo/Custom/Resources/metadata/clientDefs/Opportunity.json
fetch custom/Espo/Custom/Resources/i18n/it_IT/Opportunity.json
fetch client/custom/src/views/opportunity/helpers/appuntamento-sync.js
fetch client/custom/src/views/opportunity/record/edit.js
fetch client/custom/src/views/opportunity/record/edit-small.js

php clear_cache.php && php rebuild.php

echo "=== Fatto — Ctrl+Shift+R e riprova creazione appuntamento da calendario ==="
