#!/usr/bin/env bash
# Deploy fix generazione disponibilità da Disponibilità Ricorrente (WorkingTimeCalendar).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-disponibilita-ricorrenti-generazione-9999/tools/deploy-disponibilita-ricorrenti-generazione.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/fix-disponibilita-ricorrenti-generazione-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)
LOCAL_BACKUP="${CRM_ROOT}/backup/disponibilita-ricorrenti-generazione/server-${STAMP}"

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
  "custom/Espo/Custom/Hooks/WorkingTimeCalendar/AutoGeneraDisponibilita.php"
  "custom/Espo/Custom/Services/WorkingTimeCalendarDisponibilitaGenerator.php"
  "custom/Espo/Custom/Resources/metadata/hooks/WorkingTimeCalendar.json"
  "custom/Espo/Custom/Resources/metadata/clientDefs/WorkingTimeCalendar.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/WorkingTimeCalendar.json"
  "client/custom/src/views/working-time-calendar/record/detail.js"
)

for rel in "${FILES[@]}"; do
  backup_if_exists "${rel}"
done

echo "=== Download da ${BRANCH} ==="
for rel in "${FILES[@]}"; do
  dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=${STAMP}" -o "${dest}"
  echo "OK ${rel}"
done

echo ""
echo "=== Prossimo passo (sul server) ==="
echo "  cd ${CRM_ROOT} && php clear_cache.php && php rebuild.php"
echo ""
echo "Poi apri la Disponibilità Ricorrente e clicca «Genera Disponibilità»"
echo "oppure usa Disponibilità → Disponibilità Ricorrenti → Genera."
