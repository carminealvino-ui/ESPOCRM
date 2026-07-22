#!/usr/bin/env bash
# Fix: da calendario Durata 1h30 ma orario fine ancora +30m.
# Allinea dateEnd = dateStart + 1h30.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-appuntamento-durata-orario-9999/tools/deploy-fix-appuntamento-durata-orario.sh?t=$(date +%s)" \
#     -o tools/deploy-fix-appuntamento-durata-orario.sh
#   bash tools/deploy-fix-appuntamento-durata-orario.sh

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/fix-appuntamento-durata-orario-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

FILES=(
  "client/custom/src/helpers/appuntamento-duration.js"
  "client/custom/src/views/calendar/calendar.js"
  "client/custom/src/views/calendar/modals/edit.js"
  "client/custom/src/views/fields/appuntamento-duration.js"
  "client/custom/src/views/appuntamento/record/edit-small.js"
  "custom/Espo/Custom/client/custom/src/helpers/appuntamento-duration.js"
  "custom/Espo/Custom/client/custom/src/views/calendar/calendar.js"
  "custom/Espo/Custom/client/custom/src/views/calendar/modals/edit.js"
  "custom/Espo/Custom/client/custom/src/views/fields/appuntamento-duration.js"
  "custom/Espo/Custom/client/custom/src/views/appuntamento/record/edit-small.js"
  "custom/Espo/Custom/Resources/client/custom/src/helpers/appuntamento-duration.js"
  "custom/Espo/Custom/Resources/client/custom/src/views/calendar/calendar.js"
  "custom/Espo/Custom/Resources/client/custom/src/views/calendar/modals/edit.js"
  "custom/Espo/Custom/Resources/client/custom/src/views/fields/appuntamento-duration.js"
  "custom/Espo/Custom/Resources/client/custom/src/views/appuntamento/record/edit-small.js"
  "custom/Espo/Custom/Resources/metadata/clientDefs/Calendar.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json"
)

echo "=== Fix durata/orario Appuntamento calendario → ${CRM_ROOT} ==="
echo "Branch: ${BRANCH}"
echo ""

cd "${CRM_ROOT}"

for rel in "${FILES[@]}"; do
  target="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${target}")"
  curl -fsSL -o "${target}" "${BASE}/${rel}?t=$(date +%s)"
  echo "OK ${rel}"
done

echo ""
grep -q 'createEvent' "${CRM_ROOT}/client/custom/src/views/calendar/calendar.js" || {
  echo "ERRORE: calendar.js senza override createEvent" >&2
  exit 1
}
grep -q 'ensureCalendarDefaultDuration' "${CRM_ROOT}/client/custom/src/views/calendar/modals/edit.js" || {
  echo "ERRORE: modal edit senza ensureCalendarDefaultDuration" >&2
  exit 1
}
grep -q 'needsDefaultDateEnd' "${CRM_ROOT}/client/custom/src/views/fields/appuntamento-duration.js" || {
  echo "ERRORE: appuntamento-duration senza needsDefaultDateEnd" >&2
  exit 1
}
grep -q 'custom:views/fields/appuntamento-duration' \
  "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json" || {
  echo "ERRORE: entityDefs senza view durata custom" >&2
  exit 1
}
echo "OK verifiche file"

echo ""
if [[ -f "${CRM_ROOT}/clear_cache.php" ]]; then
  php clear_cache.php || true
fi
if [[ -f "${CRM_ROOT}/rebuild.php" ]]; then
  php rebuild.php || true
elif [[ -f "${CRM_ROOT}/command.php" ]]; then
  php command.php clearCache || true
fi

echo ""
echo "=== Deploy completato ==="
echo "Verifica: Calendario → click slot 17:00 → Date End 18:30 e Durata 1h 30m"
echo "Poi Ctrl+Shift+R (hard refresh) nel browser."
