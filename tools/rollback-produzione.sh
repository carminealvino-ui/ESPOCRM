#!/usr/bin/env bash
# Ripristino da custom/backup-layouts/YYYYMMDD-HHMMSS/
#
#   bash tools/rollback-produzione.sh                  # ultimo backup
#   bash tools/rollback-produzione.sh 20260529-143000
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BACKUP_ROOT="${CRM_ROOT}/custom/backup-layouts"

if [[ ! -d "${BACKUP_ROOT}" ]]; then
  echo "ERRORE: nessuna cartella ${BACKUP_ROOT}"
  exit 1
fi

pick_stamp() {
  if [[ -n "${1:-}" ]]; then
    echo "$1"
    return
  fi
  ls -1 "${BACKUP_ROOT}" 2>/dev/null | grep -E '^[0-9]{8}-[0-9]{6}$' | sort -r | head -1
}

STAMP="$(pick_stamp "${1:-}")"
SRC="${BACKUP_ROOT}/${STAMP}"

if [[ ! -d "${SRC}" ]] || [[ ! -f "${SRC}/manifest.txt" ]]; then
  echo "ERRORE: backup non trovato: ${SRC}"
  echo "Disponibili:"
  ls -1 "${BACKUP_ROOT}" 2>/dev/null | grep -E '^[0-9]{8}-' || true
  exit 1
fi

echo "Ripristino da: ${SRC}"
cat "${SRC}/manifest.txt"
echo ""
read -r -p "Sovrascrivere i file sul CRM? [y/N] " confirm
if [[ "${confirm}" != "y" && "${confirm}" != "Y" ]]; then
  echo "Annullato."
  exit 0
fi

cd "${CRM_ROOT}"
LIST="${SRC}/files.list"
if [[ ! -f "${LIST}" ]]; then
  echo "ERRORE: ${LIST} mancante (backup vecchio formato)"
  exit 1
fi

while IFS= read -r rel; do
  [[ -z "${rel}" ]] && continue
  [[ "${rel}" == \#* ]] && continue
  if [[ -f "${SRC}/${rel}" ]]; then
    mkdir -p "$(dirname "${CRM_ROOT}/${rel}")"
    cp -a "${SRC}/${rel}" "${CRM_ROOT}/${rel}"
    echo "Ripristinato ${rel}"
  fi
done < "${LIST}"

php command.php rebuild
rm -rf data/cache/*
echo "Rollback completato (${STAMP}). Ctrl+F5 nel browser."
