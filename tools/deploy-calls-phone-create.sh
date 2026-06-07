#!/usr/bin/env bash
# Click telefono Prospect/Lead/Account/Contact → crea Contatto telefonico (Call).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/calls-phone-create-9999/tools/deploy-calls-phone-create.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/calls-phone-create-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

FILES=(
  "client/custom/src/helpers/call-create-from-record.js"
  "client/custom/src/views/fields/phone-create-call.js"
  "client/custom/src/views/fields/telefono-dial-call.js"
  "client/custom/src/views/fields/whatsapp-create-call.js"
  "client/custom/src/views/fields/foreign-whatsapp-create-call.js"
  "custom/Espo/Custom/Resources/metadata/fields/phone.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Prospect.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Lead.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Account.json"
  "custom/Espo/Custom/Resources/metadata/formula/Call.json"
)

echo "=== Deploy Call da click telefono → ${CRM_ROOT} (branch ${BRANCH}) ==="

for rel in "${FILES[@]}"; do
  target="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${target}")"
  curl -fsSL -o "${target}" "${BASE}/${rel}?t=$(date +%s)"
  echo "OK ${rel}"
done

if [[ -f "${CRM_ROOT}/clear_cache.php" ]]; then
  (cd "${CRM_ROOT}" && php clear_cache.php && php rebuild.php)
elif [[ -f "${CRM_ROOT}/command.php" ]]; then
  (cd "${CRM_ROOT}" && php command.php rebuild && php command.php clearCache)
fi

echo ""
echo "Fatto. Ctrl+F5 nel browser."
echo "Clic su telefono o WhatsApp in Prospect / Lead / Account / Contact → modale Contatto telefonico."
