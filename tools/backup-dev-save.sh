#!/usr/bin/env bash
# Salva backup in backup_dev/{Entità}/{AGGIORNAMENTO}/
# Nome file: {DATA}_{FIX}_{AGGIORNAMENTO}_{OBIETTIVO}.{ext}
#
#   bash tools/backup-dev-save.sh Appuntamento duplica-appuntamento hooks GlobalLogic.php
set -euo pipefail

slug() {
  echo "$1" | tr '[:upper:]' '[:lower:]' | tr ' _/' '-' | tr -cd 'a-z0-9.-'
}

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
ENTITY="${1:?Entità es. Appuntamento}"
FIX_RAW="${2:?FIX es. duplica-appuntamento}"
AGGIORNAMENTO_RAW="${3:?AGGIORNAMENTO es. hooks | layouts | entityDefs}"
REL="${4:?File sorgente es. GlobalLogic.php}"

DATA="$(date +%Y%m%d-%H%M%S)"
FIX="$(slug "${FIX_RAW}")"
AGGIORNAMENTO="$(slug "${AGGIORNAMENTO_RAW}")"

DEST_DIR="${CRM_ROOT}/backup_dev/${ENTITY}/${AGGIORNAMENTO}"
mkdir -p "${DEST_DIR}"

if [[ "${AGGIORNAMENTO}" == "hooks" ]]; then
  SRC="${CRM_ROOT}/custom/Espo/Custom/Hooks/${ENTITY}/${REL}"
elif [[ "${AGGIORNAMENTO}" == "services" ]]; then
  SRC="${CRM_ROOT}/custom/Espo/Custom/Services/${REL}"
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
  META_KIND="${AGGIORNAMENTO_RAW}"
  case "${AGGIORNAMENTO}" in
    entitydefs) META_KIND="entityDefs" ;;
    clientdefs) META_KIND="clientDefs" ;;
    logicdefs) META_KIND="logicDefs" ;;
    recorddefs) META_KIND="recordDefs" ;;
    selectdefs) META_KIND="selectDefs" ;;
    acldefs) META_KIND="aclDefs" ;;
  esac
  SRC="${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/${META_KIND}/${REL}"
  if [[ -f "${SRC}" ]]; then
    AGGIORNAMENTO="metadata-${AGGIORNAMENTO}"
    DEST_DIR="${CRM_ROOT}/backup_dev/${ENTITY}/${AGGIORNAMENTO}"
    mkdir -p "${DEST_DIR}"
  elif [[ -f "${CRM_ROOT}/custom/Espo/Custom/Resources/${META_KIND}/${REL}" ]]; then
    SRC="${CRM_ROOT}/custom/Espo/Custom/Resources/${META_KIND}/${REL}"
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
