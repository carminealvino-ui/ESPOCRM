#!/usr/bin/env bash
# Fix durata Appuntamento da calendario: sempre 1h30 (non span calendario + default).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-appuntamento-durata-calendario-v2-9999/tools/deploy-fix-appuntamento-durata-calendario.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/fix-appuntamento-durata-calendario-v2-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

echo "=== Fix durata Appuntamento calendario → ${CRM_ROOT} ==="

FILES=(
  "client/custom/src/helpers/appuntamento-prospect-sync.js"
  "client/custom/src/views/appuntamento/fields/duration.js"
  "client/custom/src/views/appuntamento/record/edit-small.js"
  "client/custom/src/views/appuntamento/record/edit.js"
  "client/custom/src/views/calendar/calendar.js"
  "client/custom/src/views/calendar/modals/edit.js"
  "custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/metadata/clientDefs/Calendar.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json"
)

for rel in "${FILES[@]}"; do
  target="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${target}")"
  curl -fsSL -o "${target}" "${BASE}/${rel}?t=$(date +%s)"
  echo "OK ${rel}"
done

grep -q 'custom:views/appuntamento/fields/duration' "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json" || {
  echo "ERRORE: entityDefs Appuntamento senza campo duration custom" >&2
  exit 1
}

grep -q 'custom:views/calendar/calendar' "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/clientDefs/Calendar.json" || {
  echo "ERRORE: Calendar.json senza calendarView custom" >&2
  exit 1
}

(cd "${CRM_ROOT}" && php command.php rebuild && php command.php clearCache)

echo ""
echo "Verifica: Calendario → nuovo Appuntamento 17:00 → fine 18:30, Durata 1h 30m"
echo "Poi Ctrl+Shift+R nel browser."
