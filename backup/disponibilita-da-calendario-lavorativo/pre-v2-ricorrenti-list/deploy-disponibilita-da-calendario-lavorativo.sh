#!/usr/bin/env bash
# Deploy: generazione Disponibilità da Calendario Lavorativo.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/disponibilita-da-calendario-lavorativo-9999/tools/deploy-disponibilita-da-calendario-lavorativo.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/disponibilita-da-calendario-lavorativo-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

FILES=(
  "custom/Espo/Custom/Services/WorkingTimeCalendarDisponibilitaGenerator.php"
  "custom/Espo/Custom/Actions/WorkingTimeCalendar/GeneraDisponibilita.php"
  "custom/Espo/Custom/Controllers/WorkingTimeCalendar.php"
  "custom/Espo/Custom/Resources/metadata/entityDefs/WorkingTimeCalendar.json"
  "custom/Espo/Custom/Resources/metadata/clientDefs/WorkingTimeCalendar.json"
  "custom/Espo/Custom/Resources/metadata/app/actions.json"
  "custom/Espo/Custom/Resources/layouts/WorkingTimeCalendar/detail.json"
  "custom/Espo/Custom/Resources/layouts/WorkingTimeCalendar/edit.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/WorkingTimeCalendar.json"
  "custom/Espo/Custom/Resources/i18n/en_US/WorkingTimeCalendar.json"
  "client/custom/src/views/working-time-calendar/record/detail.js"
)

for rel in "${FILES[@]}"; do
  target="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${target}")"
  curl -fsSL -o "${target}" "${BASE}/${rel}?t=$(date +%s)"
  echo "OK ${target}"
done

if [[ -f "${CRM_ROOT}/clear_cache.php" ]]; then
  (cd "${CRM_ROOT}" && php clear_cache.php && php rebuild.php)
elif [[ -f "${CRM_ROOT}/command.php" ]]; then
  (cd "${CRM_ROOT}" && php command.php rebuild && php command.php clearCache)
fi

echo ""
echo "Fatto. Aprire un Calendario Lavorativo, compilare il pannello"
echo "\"Generazione Disponibilità\" (date, utenti, azienda) e cliccare"
echo "\"Genera Disponibilità\". Ctrl+F5 nel browser."
