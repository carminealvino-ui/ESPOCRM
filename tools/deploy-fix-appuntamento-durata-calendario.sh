#!/usr/bin/env bash
# Fix durata Appuntamento da calendario: sempre 1h30 (non span calendario + default).
#
# PASSO 0 — backup obbligatorio in backup_dev/:
#   cd ~/public_html/crm/mec-group
#   bash tools/backup-dev-batch.sh appuntamento-durata-calendario \
#     --manifest tools/backup-manifests/appuntamento-durata-calendario.files
#
# PASSO 1 — deploy (salvare su disco, NON pipe):
#   curl -fsSL ".../deploy-fix-appuntamento-durata-calendario.sh" -o tools/deploy-fix-appuntamento-durata-calendario.sh
#   bash tools/deploy-fix-appuntamento-durata-calendario.sh

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/fix-appuntamento-durata-calendario-v2-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
FIX_TAG="appuntamento-durata-calendario"

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

echo "=== Fix durata Appuntamento calendario → ${CRM_ROOT} ==="

has_backup() {
  local sessions="${CRM_ROOT}/backup_dev/_sessions"
  [[ -d "${sessions}" ]] || return 1
  local latest
  latest="$(find "${sessions}" -maxdepth 1 -type d -name "*_${FIX_TAG}" 2>/dev/null | sort -r | head -1)"
  [[ -n "${latest}" && -f "${latest}/manifest.txt" && -f "${latest}/files.list" ]]
}

if [[ "${SKIP_BACKUP_CHECK:-}" != "1" ]] && ! has_backup; then
  echo ""
  echo "PASSO 0 — esegui prima il backup in backup_dev/:"
  echo "  cd ${CRM_ROOT}"
  echo "  bash tools/backup-dev-batch.sh ${FIX_TAG} \\"
  echo "    --manifest tools/backup-manifests/appuntamento-durata-calendario.files"
  echo ""
  echo "Poi:"
  echo "  bash tools/deploy-fix-appuntamento-durata-calendario.sh"
  echo ""
  echo "(Solo emergenza: SKIP_BACKUP_CHECK=1 bash tools/deploy-fix-appuntamento-durata-calendario.sh)"
  exit 1
fi

if has_backup; then
  latest="$(find "${CRM_ROOT}/backup_dev/_sessions" -maxdepth 1 -type d -name "*_${FIX_TAG}" 2>/dev/null | sort -r | head -1)"
  echo "Backup rilevato: ${latest#${CRM_ROOT}/}"
fi
echo ""

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

if [[ -f "${CRM_ROOT}/clear_cache.php" ]]; then
  (cd "${CRM_ROOT}" && php clear_cache.php && php rebuild.php)
elif [[ -f "${CRM_ROOT}/command.php" ]]; then
  (cd "${CRM_ROOT}" && php command.php rebuild && php command.php clearCache)
fi

echo ""
echo "Verifica: Calendario → nuovo Appuntamento 17:00 → fine 18:30, Durata 1h 30m"
echo "Poi Ctrl+Shift+R nel browser."
echo ""
echo "Rollback: copiare i file da backup_dev/_sessions/*_${FIX_TAG}/ verso i path originali."
