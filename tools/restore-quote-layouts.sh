#!/usr/bin/env bash
# Ripristina layout Quote dall'ultimo backup in custom/backup-layouts/ (sul server CRM).
#
#   cd ~/public_html/crm/mec-group
#   bash tools/restore-quote-layouts.sh              # ultimo backup
#   bash tools/restore-quote-layouts.sh 20260529-003200   # cartella specifica
#
# NON usare nomi fittizi tipo ULTIMA_DATA o ULTIMO: sono solo esempi nella documentazione.
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BACKUP_ROOT="${CRM_ROOT}/custom/backup-layouts"
LAYOUT_DEST="${CRM_ROOT}/custom/Espo/Custom/Resources/layouts/Quote"

if [[ ! -d "${BACKUP_ROOT}" ]]; then
  echo "ERRORE: nessun backup in ${BACKUP_ROOT} (non esiste ancora)."
  echo ""
  echo "Scarica gli script sul server:"
  echo "  curl -fsSL \"https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/quote-prezzi-iva-inclusa-9999/tools/bootstrap-server-tools.sh?t=\$(date +%s)\" | bash"
  echo ""
  if [[ -d "${LAYOUT_DEST}" ]]; then
    STAMP="$(date +%Y%m%d-%H%M%S)"
    mkdir -p "${BACKUP_ROOT}/${STAMP}/Quote"
    cp -a "${LAYOUT_DEST}/." "${BACKUP_ROOT}/${STAMP}/Quote/"
    echo "Salvato comunque lo stato ATTUALE in: ${BACKUP_ROOT}/${STAMP}/Quote"
    echo "(non c'è un backup vecchio da ripristinare — solo Layout Manager o apply-quote-detail-prezzi-sample.sh)"
  fi
  exit 1
fi

pick_backup() {
  if [[ -n "${1:-}" ]]; then
    local candidate="${BACKUP_ROOT}/${1}/Quote"
    if [[ -d "${candidate}" ]]; then
      echo "${candidate}"
      return 0
    fi
    echo "ERRORE: backup non trovato: ${candidate}" >&2
    exit 1
  fi

  local latest
  latest="$(find "${BACKUP_ROOT}" -mindepth 2 -maxdepth 2 -type d -name Quote 2>/dev/null | sort -r | head -1)"
  if [[ -z "${latest}" ]]; then
    echo "ERRORE: nessun backup Quote in ${BACKUP_ROOT}" >&2
    echo "Cartelle presenti:" >&2
    ls -la "${BACKUP_ROOT}" >&2 || true
    exit 1
  fi
  echo "${latest}"
}

SRC="$(pick_backup "${1:-}")"
STAMP="$(basename "$(dirname "${SRC}")")"

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
echo "Ripristinato da backup ${STAMP}. Ricarica il browser (Ctrl+F5)."
