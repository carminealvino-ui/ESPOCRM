#!/usr/bin/env bash
# Popup esito Call: scelta Chiamata / WhatsApp nel promemoria.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/call-esito-canale-contatto-9999/tools/deploy-call-esito-canale-contatto.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/call-esito-canale-contatto-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)
LOCAL_BACKUP="${CRM_ROOT}/backup/call-esito-canale-contatto/server-${STAMP}"

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
  "client/custom/src/views/appuntamento/popup-notification.js"
  "client/custom/src/views/fields/call-canale-contatto.js"
  "client/custom/src/helpers/call-esito-popup-defaults.js"
  "client/custom/res/templates/fields/call-canale-contatto/edit.tpl"
  "custom/Espo/Custom/Resources/layouts/Call/detailEsitoPopup.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Call.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Call.json"
  "custom/Espo/Custom/Resources/i18n/en_US/Call.json"
  "custom/Espo/Custom/Services/AppuntamentoPendingCallCreator.php"
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
echo "Nel popup Contatto Telefonico: tipologia Richiamo su Opportunità Generata e testo standard in descrizione."
