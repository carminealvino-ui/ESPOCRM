#!/usr/bin/env bash
# Rollback: ripristina versione v1 (pulsante su dettaglio calendario, utenti manuali).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL ".../tools/rollback-disponibilita-da-calendario-lavorativo.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/disponibilita-da-calendario-lavorativo-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
BACKUP="backup/disponibilita-da-calendario-lavorativo/pre-v2-ricorrenti-list"

restore() {
  local rel="$1"
  local src="${BACKUP}/$(basename "$rel")"
  local dest="${CRM_ROOT}/${rel}"

  case "$rel" in
    custom/Espo/Custom/Resources/metadata/clientDefs/WorkingTimeCalendar.json)
      src="${BACKUP}/clientDefs.WorkingTimeCalendar.json"
      ;;
    custom/Espo/Custom/Resources/i18n/it_IT/WorkingTimeCalendar.json)
      src="${BACKUP}/i18n.it_IT.WorkingTimeCalendar.json"
      ;;
    custom/Espo/Custom/Resources/metadata/entityDefs/WorkingTimeCalendar.json)
      src="${BACKUP}/WorkingTimeCalendar.json"
      ;;
    custom/Espo/Custom/Resources/metadata/clientDefs/Disponibilita.json)
      src="${BACKUP}/Disponibilita.json"
      ;;
    custom/Espo/Custom/Controllers/Disponibilita.php)
      src="${BACKUP}/Disponibilita.php"
      ;;
  esac

  if [[ ! -f "${CRM_ROOT}/${BACKUP}/$(basename "$src")" ]] && [[ ! -f "${CRM_ROOT}/${src}" ]]; then
    curl -fsSL "${BASE}/${src}?t=$(date +%s)" -o "/tmp/rollback-$(basename "$src")"
    src="/tmp/rollback-$(basename "$src")"
  elif [[ -f "${CRM_ROOT}/${src}" ]]; then
    src="${CRM_ROOT}/${src}"
  else
    curl -fsSL "${BASE}/${src}?t=$(date +%s)" -o "/tmp/rollback-$(basename "$src")"
    src="/tmp/rollback-$(basename "$src")"
  fi

  mkdir -p "$(dirname "${dest}")"
  cp -a "${src}" "${dest}"
  echo "RESTORED ${dest}"
}

echo "=== Rollback Disponibilità da calendario (v1) ==="

FILES=(
  "custom/Espo/Custom/Services/WorkingTimeCalendarDisponibilitaGenerator.php"
  "custom/Espo/Custom/Actions/WorkingTimeCalendar/GeneraDisponibilita.php"
  "custom/Espo/Custom/Controllers/WorkingTimeCalendar.php"
  "custom/Espo/Custom/Controllers/Disponibilita.php"
  "custom/Espo/Custom/Resources/metadata/entityDefs/WorkingTimeCalendar.json"
  "custom/Espo/Custom/Resources/metadata/clientDefs/WorkingTimeCalendar.json"
  "custom/Espo/Custom/Resources/metadata/clientDefs/Disponibilita.json"
  "custom/Espo/Custom/Resources/metadata/app/actions.json"
  "custom/Espo/Custom/Resources/layouts/WorkingTimeCalendar/detail.json"
  "custom/Espo/Custom/Resources/layouts/WorkingTimeCalendar/edit.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/WorkingTimeCalendar.json"
  "client/custom/src/views/working-time-calendar/record/detail.js"
)

for rel in "${FILES[@]}"; do
  restore "${rel}"
done

rm -f "${CRM_ROOT}/custom/Espo/Custom/Actions/Disponibilita/GeneraDisponibilitaRicorrenti.php"
rm -f "${CRM_ROOT}/client/custom/src/action-handlers/disponibilita/disponibilita-ricorrenti.js"
rm -f "${CRM_ROOT}/client/custom/src/views/modals/disponibilita-ricorrenti.js"
rm -f "${CRM_ROOT}/custom/Espo/Custom/Resources/layouts/WorkingTimeCalendar/detailGenerazioneDisponibilita.json"

if [[ -f "${CRM_ROOT}/command.php" ]]; then
  (cd "${CRM_ROOT}" && php command.php rebuild && php command.php clearCache)
fi

echo ""
echo "Rollback v1 completato. Ctrl+F5 nel browser."
