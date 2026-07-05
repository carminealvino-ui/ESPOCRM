#!/usr/bin/env bash
# Hotfix: nasconde promemoria popup per entità Disponibilita.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/invito-a-fatturare-selezione-9999/tools/deploy-fix-disponibilita-popup.sh?t=$(date +%s)" | bash
#   php clear_cache.php

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/invito-a-fatturare-selezione-9999"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)
REL="custom/Espo/Custom/Tools/Activities/PopupNotificationsProvider.php"
BACKUP="${CRM_ROOT}/backup_dev/Disponibilita/popup-${STAMP}"

echo "=== Backup ==="
mkdir -p "${BACKUP}/$(dirname "${REL}")"
if [[ -f "${CRM_ROOT}/${REL}" ]]; then
  cp -a "${CRM_ROOT}/${REL}" "${BACKUP}/${REL}"
  echo "BACKUP ${REL}"
else
  echo "Nessun file precedente (verrà creato)"
fi

echo ""
echo "=== Download fix Disponibilita popup ==="
mkdir -p "${CRM_ROOT}/$(dirname "${REL}")"
curl -fsSL "${BASE}/${REL}?t=${STAMP}" -o "${CRM_ROOT}/${REL}"
echo "OK ${REL}"

if ! grep -q "BLOCKED_POPUP_ENTITY_TYPES" "${CRM_ROOT}/${REL}"; then
  echo "ERRORE: file scaricato senza BLOCKED_POPUP_ENTITY_TYPES" >&2
  exit 1
fi

if ! grep -q "'Disponibilita'" "${CRM_ROOT}/${REL}"; then
  echo "ERRORE: Disponibilita non in lista bloccata" >&2
  exit 1
fi

echo ""
echo "Poi: cd ${CRM_ROOT} && php clear_cache.php && rm -rf data/cache/*"
echo "Rollback: cp -a ${BACKUP}/${REL} ${CRM_ROOT}/${REL}"
