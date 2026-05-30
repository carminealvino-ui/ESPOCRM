#!/usr/bin/env bash
# Backup file prima di deploy in produzione → custom/backup-layouts/YYYYMMDD-HHMMSS/
#
#   bash tools/backup-produzione.sh
#   bash tools/backup-produzione.sh Appuntamento   # solo etichetta nel manifest
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
LABEL="${1:-deploy}"
STAMP="$(date +%Y%m%d-%H%M%S)"
DEST="${CRM_ROOT}/custom/backup-layouts/${STAMP}"

# File Appuntamento funzionante in produzione (rollback deploy).
# i18n/it_IT escluso di proposito — file etichette separato, vedi REGOLE-DEPLOY-NO-I18N.md
BACKUP_PATHS=(
  "custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/metadata/logicDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/metadata/hooks/Appuntamento.json"
  "custom/Espo/Custom/Resources/metadata/selectDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/metadata/scopes/Appuntamento.json"
  "custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php"
  "custom/Espo/Custom/Resources/layouts/Appuntamento/detail.json"
  "custom/Espo/Custom/Resources/layouts/Appuntamento/detailSmall.json"
  "custom/Espo/Custom/Resources/layouts/Appuntamento/massUpdate.json"
  "custom/Espo/Custom/Resources/layouts/Appuntamento/list.json"
  "client/custom/src/views/appuntamento/record/edit.js"
  "client/custom/src/views/appuntamento/record/edit-small.js"
  "client/custom/src/views/appuntamento/record/detail.js"
  "client/custom/src/views/fields/fornitore-partner-cascade.js"
  "client/custom/src/views/fields/product-brand-by-partner.js"
  "client/custom/src/views/fields/product-category-by-brand.js"
  "client/custom/src/views/fields/reminders-disabled.js"
  "client/custom/src/views/calendar/modals/edit.js"
  "client/custom/src/views/calendar/calendar.js"
)

cd "${CRM_ROOT}" || exit 1
mkdir -p "${DEST}"

{
  echo "stamp=${STAMP}"
  echo "label=${LABEL}"
  echo "host=$(hostname 2>/dev/null || echo unknown)"
  echo "date=$(date -Iseconds 2>/dev/null || date)"
} > "${DEST}/manifest.txt"

: > "${DEST}/files.list"
saved=0
for rel in "${BACKUP_PATHS[@]}"; do
  src="${CRM_ROOT}/${rel}"
  if [[ -f "${src}" ]]; then
    mkdir -p "${DEST}/$(dirname "${rel}")"
    cp -a "${src}" "${DEST}/${rel}"
    echo "${rel}" >> "${DEST}/files.list"
    saved=$((saved + 1))
  else
    echo "# SKIP ${rel}" >> "${DEST}/files.list"
  fi
done

echo "Backup produzione (${LABEL}): ${DEST}"
echo "File salvati: ${saved}"
echo "${STAMP}"
