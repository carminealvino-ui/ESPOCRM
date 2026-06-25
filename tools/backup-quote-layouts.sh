#!/usr/bin/env bash
# Backup layout Contratto prima di qualsiasi deploy (timestamp in custom/backup-layouts/).
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
STAMP="$(date +%Y%m%d-%H%M%S)"
DEST="${CRM_ROOT}/custom/backup-layouts/${STAMP}/Quote"

LAYOUT_SRC="${CRM_ROOT}/custom/Espo/Custom/Resources/layouts/Quote"

if [[ ! -d "${LAYOUT_SRC}" ]]; then
  echo "Nessuna cartella layout Quote da salvare."
  exit 0
fi

mkdir -p "${DEST}"
cp -a "${LAYOUT_SRC}/." "${DEST}/"
echo "Backup layout Quote: ${DEST}"
