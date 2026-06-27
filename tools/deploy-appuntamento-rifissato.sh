#!/usr/bin/env bash
# Deploy Appuntamento rifissato + fix viste (no meeting/*).
# Uso: bash tools/deploy-appuntamento-rifissato.sh ~/public_html/crm/mec-group

set -euo pipefail

CRM_ROOT="${1:-$(pwd)}"
BRANCH="${2:-cursor/appuntamento-rifissato-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

FILES=(
  "client/custom/src/views/appuntamento/detail.js"
  "client/custom/src/views/appuntamento/helpers/rifissato.js"
  "client/custom/src/views/appuntamento/modals/detail.js"
  "client/custom/src/views/appuntamento/modals/rifissato-create.js"
  "client/custom/src/views/appuntamento/popup-notification.js"
  "client/custom/src/views/appuntamento/record/detail.js"
  "client/custom/src/views/appuntamento/record/edit-small.js"
  "client/custom/src/views/appuntamento/record/edit.js"
  "client/custom/res/templates/appuntamento/popup-notification.tpl"
  "client/custom/res/templates/appuntamento/rifissato-create.tpl"
  "client/custom/css/custom-ui.css"
  "client/custom/src/helpers/call-esito-popup-defaults.js"
  "client/custom/src/views/opportunity/helpers/appuntamento-sync.js"
  "custom/Espo/Custom/Controllers/Appuntamento.php"
  "custom/Espo/Custom/Services/AppuntamentoRifissatoCreator.php"
  "custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php"
  "custom/Espo/Custom/Resources/client/custom/src/views/appuntamento/modals/detail.js"
  "custom/Espo/Custom/Resources/client/custom/src/views/appuntamento/record/edit-small.js"
  "custom/Espo/Custom/Resources/client/custom/src/views/appuntamento/record/edit.js"
  "custom/Espo/Custom/Resources/layouts/Appuntamento/detailEsitoPopup.json"
  "custom/Espo/Custom/Resources/layouts/Appuntamento/rifissatoCreate.json"
  "custom/Espo/Custom/Resources/metadata/app/popupNotifications.json"
  "custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json"
)

for rel in "${FILES[@]}"; do
  target="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${target}")"
  curl -fsSL -o "${target}" "${BASE}/${rel}"
  echo "OK ${target}"
done

echo ""
echo "Verifica che edit non punti piu' a meeting:"
grep -n "meeting" "${CRM_ROOT}/client/custom/src/views/appuntamento/record/edit.js" || echo "OK nessun meeting in edit.js"

if [[ -f "${CRM_ROOT}/clear_cache.php" ]]; then
  (cd "${CRM_ROOT}" && php clear_cache.php && php rebuild.php)
fi

echo ""
echo "Fatto. Hard refresh browser: Ctrl+Shift+R"
