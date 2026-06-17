#!/usr/bin/env bash
# Appuntamento Pending → Call automatica +2 giorni alle 9:30 (weekend → lunedì).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-appuntamento-pending-call-9999/tools/deploy-appuntamento-pending-call.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/fix-appuntamento-pending-call-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)
LOCAL_BACKUP="${CRM_ROOT}/backup/appuntamento-pending-call/server-${STAMP}"

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
  "custom/Espo/Custom/Tools/Appuntamento/PendingCallDateTime.php"
  "custom/Espo/Custom/Services/AppuntamentoPendingCallCreator.php"
  "custom/Espo/Custom/Hooks/Appuntamento/AutoCreatePendingCall.php"
  "custom/Espo/Custom/Resources/metadata/hooks/Appuntamento.json"
  "tools/backfill-pending-calls.php"
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
echo "Test: segna un Appuntamento come Svolto + sottostato Pending."
echo "Viene creata una Call pianificata +2 giorni alle 9:30 (lun se weekend), con promemoria."
echo ""
echo "Backfill appuntamenti Pending già esistenti:"
echo "  cd ${CRM_ROOT} && php tools/backfill-pending-calls.php --dry-run"
echo "  cd ${CRM_ROOT} && php tools/backfill-pending-calls.php"
