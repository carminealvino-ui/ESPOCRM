#!/usr/bin/env bash
# Fix popup richiami Call da appuntamento Pending (promemoria + cutoff).
#
# PASSO 0 — backup obbligatorio:
#   cd ~/public_html/crm/mec-group
#   bash tools/backup-dev-batch.sh pending-call-popup \
#     --manifest tools/backup-manifests/pending-call-popup.files
#
# PASSO 1 — deploy (salvare su disco, NON pipe):
#   curl -fsSL ".../deploy-pending-call-popup-fix.sh" -o tools/deploy-pending-call-popup-fix.sh
#   bash tools/deploy-pending-call-popup-fix.sh

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/fix-pending-call-popup-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
FIX_TAG="pending-call-popup"

echo "=== Fix popup Call Pending → ${CRM_ROOT} ==="

FILES=(
  "custom/Espo/Custom/Services/AppuntamentoPendingCallCreator.php"
  "custom/Espo/Custom/Hooks/Appuntamento/AutoCreatePendingCall.php"
  "custom/Espo/Custom/Hooks/Appuntamento/CreateCallFromRichiamo.php"
  "custom/Espo/Custom/Hooks/Call/RinvioRichiamo.php"
  "custom/Espo/Custom/Tools/Activities/PopupNotificationsProvider.php"
  "custom/Espo/Custom/Tools/Appuntamento/PendingCallDateTime.php"
  "custom/Espo/Custom/Hooks/Call/NormalizeAutoPendingFields.php"
  "custom/Espo/Custom/Resources/metadata/formula/Call.json"
  "custom/Espo/Custom/Resources/layouts/Appuntamento/detailEsitoPopup.json"
  "custom/Espo/Custom/Resources/layouts/Call/detailEsitoPopup.json"
  "client/custom/src/helpers/call-esito-popup-defaults.js"
  "client/custom/src/views/appuntamento/popup-notification.js"
  "tools/fix-call-assignment-from-appuntamento.php"
  "tools/audit-pending-call-candidates.php"
  "tools/backfill-pending-calls.php"
  "tools/diagnose-pending-call-one.php"
)

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
  echo "    --manifest tools/backup-manifests/pending-call-popup.files"
  echo ""
  echo "Poi:"
  echo "  bash tools/deploy-pending-call-popup-fix.sh"
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

grep -q "2026-06-30e" "${CRM_ROOT}/custom/Espo/Custom/Services/AppuntamentoPendingCallCreator.php" || {
  echo "ERRORE: AppuntamentoPendingCallCreator.php non aggiornato (atteso CREATOR_VERSION 2026-06-30e)" >&2
  exit 1
}

grep -q "syncPopupReminders" "${CRM_ROOT}/custom/Espo/Custom/Services/AppuntamentoPendingCallCreator.php" || {
  echo "ERRORE: AppuntamentoPendingCallCreator.php non aggiornato" >&2
  exit 1
}

(cd "${CRM_ROOT}" && php command.php rebuild && php command.php clearCache)

echo ""
echo "Audit appuntamenti Pending senza Call:"
echo "  php ${CRM_ROOT}/tools/audit-pending-call-candidates.php"
echo "Crea Call mancanti (backfill):"
echo "  php ${CRM_ROOT}/tools/backfill-pending-calls.php --create"
echo "Ripara promemoria sulle Call già create:"
echo "  php ${CRM_ROOT}/tools/fix-call-assignment-from-appuntamento.php"
echo ""
echo "Poi Ctrl+Shift+R nel browser."
