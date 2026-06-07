#!/usr/bin/env bash
# Deploy v2: Disponibilità Ricorrenti da lista Disponibilità.
# Utenti automatici dal calendario; area e collaboratori nel pannello generazione.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/disponibilita-da-calendario-lavorativo-9999/tools/deploy-disponibilita-da-calendario-lavorativo.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/disponibilita-da-calendario-lavorativo-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)
LOCAL_BACKUP="${CRM_ROOT}/backup/disponibilita-da-calendario-lavorativo/server-${STAMP}"

echo "=== Backup locale pre-deploy in ${LOCAL_BACKUP} ==="
mkdir -p "${LOCAL_BACKUP}"

backup_if_exists() {
  local rel="$1"
  local src="${CRM_ROOT}/${rel}"
  if [[ -f "${src}" ]]; then
    mkdir -p "${LOCAL_BACKUP}/$(dirname "${rel}")"
    cp -a "${src}" "${LOCAL_BACKUP}/${rel}"
    echo "BACKUP ${rel}"
  fi
}

FILES=(
  "custom/Espo/Custom/Hooks/WorkingTimeCalendar/SetName.php"
  "custom/Espo/Custom/Hooks/Disponibilita/SetName.php"
  "custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php"
  "custom/Espo/Custom/Resources/metadata/hooks/Disponibilita.json"
  "custom/Espo/Custom/Resources/metadata/hooks/WorkingTimeCalendar.json"
  "custom/Espo/Custom/Services/WorkingTimeCalendarDisponibilitaGenerator.php"
  "custom/Espo/Custom/Actions/WorkingTimeCalendar/GeneraDisponibilita.php"
  "custom/Espo/Custom/Actions/Disponibilita/GeneraDisponibilitaRicorrenti.php"
  "custom/Espo/Custom/Controllers/WorkingTimeCalendar.php"
  "custom/Espo/Custom/Controllers/Disponibilita.php"
  "custom/Espo/Custom/Resources/metadata/entityDefs/WorkingTimeCalendar.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Disponibilita.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/ProductBrand.json"
  "custom/Espo/Custom/Resources/metadata/clientDefs/WorkingTimeCalendar.json"
  "custom/Espo/Custom/Resources/metadata/clientDefs/Disponibilita.json"
  "custom/Espo/Custom/Resources/metadata/app/actions.json"
  "custom/Espo/Custom/Resources/layouts/WorkingTimeCalendar/detail.json"
  "custom/Espo/Custom/Resources/layouts/WorkingTimeCalendar/edit.json"
  "custom/Espo/Custom/Resources/layouts/WorkingTimeCalendar/detailGenerazioneDisponibilita.json"
  "custom/Espo/Custom/Resources/layouts/WorkingTimeCalendar/detailSmall.json"
  "custom/Espo/Custom/Resources/layouts/Disponibilita/detail.json"
  "custom/Espo/Custom/Resources/layouts/Disponibilita/detailSmall.json"
  "custom/Espo/Custom/Resources/layouts/Disponibilita/list.json"
  "custom/Espo/Custom/Resources/layouts/Disponibilita/listSmall.json"
  "custom/Espo/Custom/Resources/layouts/Disponibilita/filters.json"
  "custom/Espo/Custom/Resources/layouts/ProductBrand/detail.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/WorkingTimeCalendar.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Disponibilita.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/ProductBrand.json"
  "custom/Espo/Custom/Resources/i18n/en_US/WorkingTimeCalendar.json"
  "custom/Espo/Custom/Resources/i18n/en_US/Disponibilita.json"
  "custom/Espo/Custom/Resources/i18n/en_US/ProductBrand.json"
  "client/custom/src/action-handlers/disponibilita/disponibilita-ricorrenti.js"
  "client/custom/src/views/modals/disponibilita-ricorrenti.js"
  "client/custom/src/views/modals/working-time-calendar-edit.js"
  "tools/rollback-disponibilita-da-calendario-lavorativo.sh"
)

for rel in "${FILES[@]}"; do
  backup_if_exists "${rel}"
done

echo ""
echo "=== Deploy file da branch ${BRANCH} ==="

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
echo "Deploy v2 completato."
echo "Backup locale: ${LOCAL_BACKUP}"
echo "Rollback repo v1: bash tools/rollback-disponibilita-da-calendario-lavorativo.sh"
echo ""
echo "Uso: lista Disponibilità → pulsante «Disponibilità Ricorrenti»."
echo "Ctrl+F5 nel browser."
