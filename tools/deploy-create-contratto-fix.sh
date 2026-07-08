#!/usr/bin/env bash
# Hotfix dedicato Crea Contratto (Opportunity/action/createContratto).
#
# Uso:
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-calendario-appuntamento-9999/tools/deploy-create-contratto-fix.sh" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/fix-calendario-appuntamento-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"
TS="$(date +%s)"

cd "${CRM_ROOT}"

mkdir -p backup/custom/Espo/Custom/Controllers
if [[ -f custom/Espo/Custom/Controllers/Opportunity.php ]]; then
  cp custom/Espo/Custom/Controllers/Opportunity.php "backup/custom/Espo/Custom/Controllers/Opportunity.php.${TS}.bak"
fi

fetch() {
  local path="$1"
  mkdir -p "$(dirname "$path")"
  curl -fL --retry 8 --retry-all-errors --retry-delay 2 --retry-max-time 120 \
    "${BASE}/${path}" -o "${path}"
  echo "OK ${path}"
}

# Stack minimo necessario per endpoint createContratto.
fetch custom/Espo/Custom/Controllers/Opportunity.php
fetch custom/Espo/Custom/Actions/Opportunity/CreateContratto.php
fetch custom/Espo/Custom/Services/ReferenteContactService.php
fetch custom/Espo/Custom/Services/LeadProspectSync.php
fetch custom/Espo/Custom/Resources/client/custom/src/views/quote/fields/item-list.js
fetch custom/Espo/Custom/Resources/metadata/app/client.json
fetch custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json

php clear_cache.php
php rebuild.php

echo "=== Fatto: deploy createContratto completato ==="
