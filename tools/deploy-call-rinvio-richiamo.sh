#!/usr/bin/env bash
# Rinvio richiamo Call: popup + backend reschedule/follow-up.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/call-rinvio-richiamo-9999/tools/deploy-call-rinvio-richiamo.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/call-rinvio-richiamo-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

echo "=== Rinvio richiamo Call → ${CRM_ROOT} ==="

FILES=(
  "custom/Espo/Custom/Services/AppuntamentoPendingCallCreator.php"
  "custom/Espo/Custom/Hooks/Call/RinvioRichiamo.php"
  "custom/Espo/Custom/Resources/layouts/Call/detailEsitoPopup.json"
  "custom/Espo/Custom/Resources/layouts/Appuntamento/detailEsitoPopup.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/metadata/logicDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/metadata/logicDefs/Call.json"
  "custom/Espo/Custom/Classes/Select/Call/PrimaryFilters/RichiamiPianificati.php"
  "custom/Espo/Custom/Resources/i18n/it_IT/Call.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Appuntamento.json"
  "client/custom/src/views/appuntamento/popup-notification.js"
  "client/custom/src/helpers/call-esito-popup-defaults.js"
)

for rel in "${FILES[@]}"; do
  target="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${target}")"
  curl -fsSL -o "${target}" "${BASE}/${rel}?t=$(date +%s)"
  echo "OK ${rel}"
done

grep -q "applyRinvioToEntity" "${CRM_ROOT}/custom/Espo/Custom/Services/AppuntamentoPendingCallCreator.php" || {
  echo "ERRORE: AppuntamentoPendingCallCreator.php non aggiornato" >&2
  exit 1
}

grep -q "RinvioRichiamo" "${CRM_ROOT}/custom/Espo/Custom/Hooks/Call/RinvioRichiamo.php" || {
  echo "ERRORE: hook RinvioRichiamo mancante" >&2
  exit 1
}

(cd "${CRM_ROOT}" && php command.php rebuild && php command.php clearCache)

echo ""
echo "Fatto. Nel popup Call:"
echo "  - Rinvia richiamo (stessa Call ancora Pianificata) → nuova data"
echo "  - Esito Non svolto + Rinvia richiamo → nuova Call pianificata"
echo "Poi Ctrl+Shift+R nel browser."
