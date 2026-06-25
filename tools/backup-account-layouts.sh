#!/usr/bin/env bash
# Backup layout + metadata Account prima di deploy subpanel Appuntamenti/Contratti.
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
STAMP="$(date +%Y%m%d-%H%M%S)"
DEST="${CRM_ROOT}/custom/backup-layouts/${STAMP}/Account"

PATHS=(
  "custom/Espo/Custom/Resources/layouts/Account"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Account.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/metadata/clientDefs/Account.json"
  "custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php"
)

cd "${CRM_ROOT}" || exit 1
mkdir -p "${DEST}"

for rel in "${PATHS[@]}"; do
  src="${CRM_ROOT}/${rel}"
  if [[ -e "${src}" ]]; then
    mkdir -p "${DEST}/$(dirname "${rel}")"
    cp -a "${src}" "${DEST}/${rel}"
  fi
done

echo "${STAMP}" > "${CRM_ROOT}/custom/backup-layouts/LAST_ACCOUNT_BACKUP.txt"
echo "Backup Account: ${DEST}"
