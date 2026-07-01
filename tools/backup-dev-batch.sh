#!/usr/bin/env bash
# Backup obbligatorio PRIMA di ogni modifica — più file in una sessione.
#
# Uso (path relativi alla root CRM):
#   bash tools/backup-dev-batch.sh FIX_LABEL file1 file2 ...
#
# Uso con manifest (un path per riga, # commenti ok):
#   bash tools/backup-dev-batch.sh FIX_LABEL --manifest tools/backup-manifests/google-sync.files
#
# Esempio Google Sync:
#   cd ~/public_html/crm/mec-group
#   bash tools/backup-dev-batch.sh google-sync --manifest tools/backup-manifests/google-sync.files
#
set -euo pipefail

slug() {
  echo "$1" | tr '[:upper:]' '[:lower:]' | tr ' _/' '-' | tr -cd 'a-z0-9.-'
}

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
FIX_RAW="${1:?FIX_LABEL es. google-sync}"
shift

DATA="$(date +%Y%m%d-%H%M%S)"
FIX="$(slug "${FIX_RAW}")"
SESSION_DIR="${CRM_ROOT}/backup_dev/_sessions/${DATA}_${FIX}"
mkdir -p "${SESSION_DIR}"

declare -a PATHS=()

if [[ "${1:-}" == "--manifest" ]]; then
  MANIFEST="${2:?manifest file}"
  shift 2
  while IFS= read -r line || [[ -n "${line}" ]]; do
    line="${line%%#*}"
    line="$(echo "${line}" | xargs)"
    [[ -z "${line}" ]] && continue
    PATHS+=("${line}")
  done < "${MANIFEST}"
fi

while [[ $# -gt 0 ]]; do
  PATHS+=("$1")
  shift
done

if [[ ${#PATHS[@]} -eq 0 ]]; then
  echo "ERRORE: nessun file da salvare."
  echo "Uso: bash tools/backup-dev-batch.sh FIX_LABEL path1 path2 ..."
  exit 1
fi

resolve_backup_dest() {
  local rel="$1"
  local entity="" agg="" base
  base="$(basename "${rel}")"

  if [[ "${rel}" == custom/Espo/Custom/Hooks/*/* ]]; then
    entity="$(echo "${rel}" | cut -d/ -f5)"
    agg="hooks"
  elif [[ "${rel}" == custom/Espo/Custom/Services/* ]]; then
    entity="Appuntamento"
    if [[ "${base}" != Appuntamento* ]]; then
      entity="_misc"
      agg="services"
    else
      agg="services"
    fi
  elif [[ "${rel}" == custom/Espo/Custom/Resources/layouts/*/* ]]; then
    entity="$(echo "${rel}" | cut -d/ -f7)"
    agg="layouts"
  elif [[ "${rel}" == custom/Espo/Custom/Resources/metadata/entityDefs/*.json ]]; then
    entity="$(basename "${base}" .json)"
    agg="metadata-entityDefs"
  elif [[ "${rel}" == custom/Espo/Custom/Resources/metadata/clientDefs/*.json ]]; then
    entity="$(basename "${base}" .json)"
    agg="metadata-clientDefs"
  elif [[ "${rel}" == custom/Espo/Custom/Resources/metadata/hooks/*.json ]]; then
    entity="$(basename "${base}" .json)"
    agg="metadata-hooks"
  elif [[ "${rel}" == custom/Espo/Modules/Google/* ]]; then
    entity="_misc"
    agg="google-module"
  elif [[ "${rel}" == client/custom/src/views/*/* ]]; then
    entity="$(echo "${rel}" | cut -d/ -f5)"
    entity="$(echo "${entity:0:1}" | tr '[:lower:]' '[:upper:]')${entity:1}"
    local sub
    sub="$(echo "${rel}" | cut -d/ -f6)"
    agg="client-${sub}"
  elif [[ "${rel}" == client/custom/src/helpers/* ]]; then
    entity="_misc"
    agg="client-helpers"
  elif [[ "${rel}" == client/custom/src/views/fields/* ]]; then
    entity="_misc"
    agg="client-fields"
  elif [[ "${rel}" == tools/* ]]; then
    entity="_scripts"
    agg="tools"
  else
    entity="_misc"
    agg="other"
  fi

  local name="${base%.*}"
  local ext="${base##*.}"
  local obiettivo
  obiettivo="$(slug "${name}")"
  local dest_dir="${CRM_ROOT}/backup_dev/${entity}/${agg}"
  local out

  if [[ "${name}" == "${ext}" ]]; then
    out="${dest_dir}/${DATA}_${FIX}_${agg}_${obiettivo}"
  else
    out="${dest_dir}/${DATA}_${FIX}_${agg}_${obiettivo}.${ext}"
  fi

  echo "${dest_dir}|${out}"
}

{
  echo "stamp=${DATA}"
  echo "fix=${FIX}"
  echo "host=$(hostname 2>/dev/null || echo unknown)"
  echo "date=$(date -Iseconds 2>/dev/null || date)"
  echo "session=${SESSION_DIR}"
} > "${SESSION_DIR}/manifest.txt"

saved=0
skipped=0

for rel in "${PATHS[@]}"; do
  src="${CRM_ROOT}/${rel}"
  if [[ ! -f "${src}" ]]; then
    echo "# SKIP (non trovato) ${rel}" >> "${SESSION_DIR}/files.list"
    skipped=$((skipped + 1))
    continue
  fi

  IFS='|' read -r dest_dir out <<< "$(resolve_backup_dest "${rel}")"
  mkdir -p "${dest_dir}"
  cp -a "${src}" "${out}"
  echo "${rel} -> ${out#${CRM_ROOT}/}" >> "${SESSION_DIR}/files.list"
  echo "OK ${rel}"
  saved=$((saved + 1))
done

echo ""
echo "=== Backup sessione completata ==="
echo "Fix:     ${FIX}"
echo "Salvati: ${saved}"
echo "Skip:    ${skipped}"
echo "Sessione: backup_dev/_sessions/${DATA}_${FIX}/"
echo "Manifest: ${SESSION_DIR}/manifest.txt"
echo ""
echo "Prossimo passo: solo ora modificare / deployare il codice."
