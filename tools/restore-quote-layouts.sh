#!/usr/bin/env bash
# Ripristina layout Quote da backup_dev/Quote/layouts-snapshots/
#
#   bash tools/restore-quote-layouts.sh
#   bash tools/restore-quote-layouts.sh 20260529-003200
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BACKUP_ROOT="${CRM_ROOT}/backup_dev/Quote/layouts-snapshots"
LEGACY_ROOT="${CRM_ROOT}/custom/backup-layouts"
LAYOUT_DEST="${CRM_ROOT}/custom/Espo/Custom/Resources/layouts/Quote"

if [[ ! -d "${BACKUP_ROOT}" ]] || [[ -z "$(ls -A "${BACKUP_ROOT}" 2>/dev/null)" ]]; then
  if [[ -d "${LEGACY_ROOT}" ]]; then
    echo "ATTENZIONE: uso backup legacy custom/backup-layouts/ (migrare in backup_dev/)"
    BACKUP_ROOT="${LEGACY_ROOT}"
  else
    echo "ERRORE: nessun backup in ${BACKUP_ROOT}"
    exit 1
  fi
fi

pick_backup() {
  if [[ -n "${1:-}" ]]; then
    local candidate="${BACKUP_ROOT}/${1}"
    if [[ "${BACKUP_ROOT}" == *"custom/backup-layouts"* ]]; then
      candidate="${BACKUP_ROOT}/${1}/Quote"
    fi
    if [[ -d "${candidate}" ]]; then
      echo "${candidate}"
      return 0
    fi
    echo "ERRORE: backup non trovato: ${candidate}" >&2
    exit 1
  fi

  local latest
  if [[ "${BACKUP_ROOT}" == *"custom/backup-layouts"* ]]; then
    latest="$(find "${BACKUP_ROOT}" -mindepth 2 -maxdepth 2 -type d -name Quote 2>/dev/null | sort -r | head -1)"
  else
    latest="$(find "${BACKUP_ROOT}" -mindepth 1 -maxdepth 1 -type d 2>/dev/null | sort -r | head -1)"
  fi
  if [[ -z "${latest}" ]]; then
    echo "ERRORE: nessun backup Quote in ${BACKUP_ROOT}" >&2
    exit 1
  fi
  echo "${latest}"
}

SRC="$(pick_backup "${1:-}")"
STAMP="$(basename "${SRC}")"
if [[ "${SRC}" == *"/Quote" ]]; then
  STAMP="$(basename "$(dirname "${SRC}")")"
fi

echo "Backup: ${SRC}"
echo "Destinazione: ${LAYOUT_DEST}"
read -r -p "Sovrascrivere i layout Quote attuali? [y/N] " confirm
if [[ "${confirm}" != "y" && "${confirm}" != "Y" ]]; then
  echo "Annullato."
  exit 0
fi

mkdir -p "${LAYOUT_DEST}"
cp -a "${SRC}/." "${LAYOUT_DEST}/"
cd "${CRM_ROOT}"
php command.php rebuild
rm -rf data/cache/*
echo "Ripristinato da backup ${STAMP}. Ctrl+F5."
