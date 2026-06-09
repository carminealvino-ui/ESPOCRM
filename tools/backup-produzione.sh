#!/usr/bin/env bash
# Backup file Appuntamento prima di deploy → backup_dev/Appuntamento/snapshots/
#
#   bash tools/backup-produzione.sh
#   bash tools/backup-produzione.sh Appuntamento
#
# Preferire: bash tools/backup-dev-batch.sh FIX --manifest tools/backup-manifests/....files
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
LABEL="${1:-deploy}"
STAMP="$(date +%Y%m%d-%H%M%S)"
DEST="${CRM_ROOT}/backup_dev/Appuntamento/snapshots/${STAMP}"

BACKUP_PATHS=(
  "custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json"
  "client/custom/src/views/appuntamento/record/edit.js"
  "client/custom/src/views/appuntamento/record/edit-small.js"
  "client/custom/src/views/appuntamento/record/detail.js"
  "client/custom/src/views/appuntamento/modals/detail.js"
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
