#!/usr/bin/env bash
# Calendario: click su Appuntamento apre solo il pannello Esito (modifica rapida).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/appuntamento-calendario-esito-9999/tools/deploy-appuntamento-calendario-esito.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/appuntamento-calendario-esito-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)
LOCAL_BACKUP="${CRM_ROOT}/backup/appuntamento-calendario-esito/server-${STAMP}"

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
  "client/custom/src/views/calendar/calendar.js"
  "client/custom/src/views/appuntamento/popup-notification.js"
  "client/custom/res/templates/appuntamento/popup-notification.tpl"
  "custom/Espo/Custom/Resources/layouts/Appuntamento/detailEsito.json"
  "custom/Espo/Custom/Resources/metadata/app/popupNotifications.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Appuntamento.json"
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
echo "Poi apri Calendario, clicca un Appuntamento: compare solo la sezione Esito."
echo "Il promemoria popup per Appuntamento include Stato/Sottostato/Esito/Note e si chiude solo dopo Salva."
