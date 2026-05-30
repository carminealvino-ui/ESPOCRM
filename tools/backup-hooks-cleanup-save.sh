#!/usr/bin/env bash
# Salva backup in backup/hooks_cleanup/{Entità}/{AGGIORNAMENTO}/
# Nome file: {DATA}_{FIX}_{AGGIORNAMENTO}_{OBIETTIVO}.{ext}
#
#   bash tools/backup-hooks-cleanup-save.sh Appuntamento duplica-appuntamento hooks GlobalLogic.php
#   bash tools/backup-hooks-cleanup-save.sh Quote layout-quote layouts detail.json
#   bash tools/backup-hooks-cleanup-save.sh Opportunity create-contratto client-handlers create-contratto.js
set -euo pipefail

slug() {
  echo "$1" | tr '[:upper:]' '[:lower:]' | tr ' _/' '-' | tr -cd 'a-z0-9.-'
}

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
ENTITY="${1:?Entità es. Appuntamento}"
FIX_RAW="${2:?FIX es. duplica-appuntamento o layout-quote}"
AGGIORNAMENTO_RAW="${3:?AGGIORNAMENTO es. hooks | layouts | entityDefs | client-handlers}"
REL="${4:?File sorgente es. GlobalLogic.php o detail.json}"

DATA="$(date +%Y%m%d-%H%M%S)"
FIX="$(slug "${FIX_RAW}")"
AGGIORNAMENTO="$(slug "${AGGIORNAMENTO_RAW}")"

DEST_DIR="${CRM_ROOT}/backup/hooks_cleanup/${ENTITY}/${AGGIORNAMENTO}"
mkdir -p "${DEST_DIR}"

if [[ "${AGGIORNAMENTO}" == "hooks" ]]; then
  SRC="${CRM_ROOT}/custom/Espo/Custom/Hooks/${ENTITY}/${REL}"
elif [[ "${AGGIORNAMENTO}" == "layouts" ]]; then
  SRC="${CRM_ROOT}/custom/Espo/Custom/Resources/layouts/${ENTITY}/${REL}"
elif [[ "${AGGIORNAMENTO}" == client-* ]]; then
  SUB="${AGGIORNAMENTO#client-}"
  entity_lower="$(echo "${ENTITY}" | tr '[:upper:]' '[:lower:]')"
  SRC="${CRM_ROOT}/client/custom/src/views/${entity_lower}/${SUB}/${REL}"
  if [[ ! -f "${SRC}" ]]; then
    SRC="${CRM_ROOT}/client/custom/src/${REL}"
  fi
else
  # entityDefs, logicDefs, clientDefs, formula, ...
  SRC="${CRM_ROOT}/custom/Espo/Custom/Resources/${AGGIORNAMENTO}/${REL}"
  if [[ ! -f "${SRC}" ]] && [[ -f "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/${AGGIORNAMENTO}/${REL}" ]]; then
    SRC="${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/${AGGIORNAMENTO}/${REL}"
    AGGIORNAMENTO="metadata-${AGGIORNAMENTO}"
    DEST_DIR="${CRM_ROOT}/backup/hooks_cleanup/${ENTITY}/${AGGIORNAMENTO}"
    mkdir -p "${DEST_DIR}"
  fi
fi

if [[ ! -f "${SRC}" ]]; then
  echo "ERRORE: file non trovato: ${SRC}"
  exit 1
fi

base="$(basename "${REL}")"
name="${base%.*}"
ext="${base##*.}"
OBIETTIVO="$(slug "${name}")"

if [[ "${name}" == "${ext}" ]]; then
  OUT="${DEST_DIR}/${DATA}_${FIX}_${AGGIORNAMENTO}_${OBIETTIVO}"
else
  OUT="${DEST_DIR}/${DATA}_${FIX}_${AGGIORNAMENTO}_${OBIETTIVO}.${ext}"
fi

cp -a "${SRC}" "${OUT}"
echo "Backup: ${OUT}"
echo "  DATA=${DATA} FIX=${FIX} AGGIORNAMENTO=${AGGIORNAMENTO} OBIETTIVO=${OBIETTIVO}"
