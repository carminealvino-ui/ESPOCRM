#!/usr/bin/env bash
# Fix popup richiami Call da appuntamento Pending (promemoria + cutoff).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-pending-call-popup-9999/tools/deploy-pending-call-popup-fix.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/fix-pending-call-popup-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

echo "=== Fix popup Call Pending → ${CRM_ROOT} ==="

FILES=(
  "custom/Espo/Custom/Services/AppuntamentoPendingCallCreator.php"
  "custom/Espo/Custom/Hooks/Appuntamento/AutoCreatePendingCall.php"
  "custom/Espo/Custom/Hooks/Appuntamento/CreateCallFromRichiamo.php"
  "custom/Espo/Custom/Tools/Activities/PopupNotificationsProvider.php"
  "custom/Espo/Custom/Tools/Appuntamento/PendingCallDateTime.php"
  "custom/Espo/Custom/Hooks/Call/NormalizeAutoPendingFields.php"
  "custom/Espo/Custom/Resources/metadata/formula/Call.json"
  "custom/Espo/Custom/Resources/layouts/Appuntamento/detailEsitoPopup.json"
  "client/custom/src/helpers/call-esito-popup-defaults.js"
  "tools/fix-call-assignment-from-appuntamento.php"
  "tools/audit-pending-call-candidates.php"
  "tools/backfill-pending-calls.php"
  "tools/diagnose-pending-call-one.php"
)

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
