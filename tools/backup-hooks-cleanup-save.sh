#!/usr/bin/env bash
# Copia un file live in backup/hooks_cleanup/{Entità}/{tipo}/ con timestamp.
#
#   bash tools/backup-hooks-cleanup-save.sh Appuntamento hooks GlobalLogic.php
#   bash tools/backup-hooks-cleanup-save.sh Opportunity metadata/entityDefs Opportunity.json
#   bash tools/backup-hooks-cleanup-save.sh Quote layouts detail.json
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
ENTITY="${1:?Entità es. Appuntamento}"
TYPE="${2:?Tipo: hooks | layouts | metadata/entityDefs | client/detail | ...}"
REL="${3:?Nome file, es. GlobalLogic.php o detail.json}"

STAMP="$(date +%Y%m%d-%H%M%S)"
DEST_DIR="${CRM_ROOT}/backup/hooks_cleanup/${ENTITY}/${TYPE}"
mkdir -p "${DEST_DIR}"

if [[ "${TYPE}" == "hooks" ]]; then
  SRC="${CRM_ROOT}/custom/Espo/Custom/Hooks/${ENTITY}/${REL}"
elif [[ "${TYPE}" == "layouts" ]]; then
  SRC="${CRM_ROOT}/custom/Espo/Custom/Resources/layouts/${ENTITY}/${REL}"
elif [[ "${TYPE}" == client/* ]] || [[ "${TYPE}" == client* ]]; then
  SUB="${TYPE#client/}"
  SRC="${CRM_ROOT}/client/custom/src/views/${ENTITY,,}/${SUB}/${REL}"
  if [[ ! -f "${SRC}" ]]; then
    SRC="${CRM_ROOT}/client/custom/src/${REL}"
  fi
else
  SRC="${CRM_ROOT}/custom/Espo/Custom/Resources/${TYPE}/${REL}"
fi

if [[ ! -f "${SRC}" ]]; then
  echo "ERRORE: file non trovato: ${SRC}"
  exit 1
fi

base="$(basename "${REL}")"
name="${base%.*}"
ext="${base##*.}"
OUT="${DEST_DIR}/${name}-${STAMP}"
if [[ "${name}" != "${ext}" ]]; then
  OUT="${OUT}.${ext}"
fi

cp -a "${SRC}" "${OUT}"
echo "Backup: ${OUT}"
