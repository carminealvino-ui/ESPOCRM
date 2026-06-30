#!/usr/bin/env bash
# Solo colori Disponibilità nel calendario — non tocca date, calendar.js né SQL diretto.
#
# Passo 0 (se non hai già una sessione disponibilita-calendario di oggi):
#   cd ~/public_html/crm/mec-group
#   bash tools/backup-dev-batch.sh disponibilita-calendario \
#     --manifest tools/backup-manifests/disponibilita-calendario.files
#
# Deploy:
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-disponibilita-calendario-colore-9999/tools/deploy-colori-disponibilita-calendario.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/fix-disponibilita-calendario-colore-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
FIX_TAG="disponibilita-calendario"

has_backup() {
  local sessions="${CRM_ROOT}/backup_dev/_sessions"
  [[ -d "${sessions}" ]] || return 1
  local latest
  latest="$(find "${sessions}" -maxdepth 1 -type d -name "*_${FIX_TAG}" 2>/dev/null | sort -r | head -1)"
  [[ -n "${latest}" && -f "${latest}/manifest.txt" && -f "${latest}/files.list" ]]
}

echo "=== Colori Disponibilità calendario → ${CRM_ROOT} ==="

if [[ "${SKIP_BACKUP_CHECK:-}" != "1" ]] && ! has_backup; then
  echo ""
  echo "PASSO 0 — backup obbligatorio:"
  echo "  bash tools/backup-dev-batch.sh ${FIX_TAG} --manifest tools/backup-manifests/disponibilita-calendario.files"
  exit 1
fi

if has_backup; then
  latest="$(find "${CRM_ROOT}/backup_dev/_sessions" -maxdepth 1 -type d -name "*_${FIX_TAG}" 2>/dev/null | sort -r | head -1)"
  echo "Backup: ${latest#${CRM_ROOT}/}"
fi

FILES=(
  "custom/Espo/Custom/Hooks/Disponibilita/SetName.php"
  "custom/Espo/Custom/Resources/metadata/app/calendar.json"
  "tools/data/brand-calendar-colors.json"
  "tools/backfill-brand-color-calendario.php"
)

for rel in "${FILES[@]}"; do
  target="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${target}")"
  curl -fsSL -o "${target}" "${BASE}/${rel}?t=$(date +%s)"
  echo "OK ${rel}"
done

grep -q "resolveDisponibilitaColor" "${CRM_ROOT}/custom/Espo/Custom/Hooks/Disponibilita/SetName.php" || {
  echo "ERRORE: SetName.php non aggiornato" >&2
  exit 1
}

(cd "${CRM_ROOT}" && php command.php rebuild && php command.php clearCache)

echo ""
echo "Anteprima colori brand (dry-run)..."
(cd "${CRM_ROOT}" && php tools/backfill-brand-color-calendario.php \
  --only=brands --apply-default-colors --dry-run --verbose)

echo ""
echo "Aggiornamento colori ProductBrand..."
(cd "${CRM_ROOT}" && php tools/backfill-brand-color-calendario.php \
  --only=brands --apply-default-colors --verbose)

echo ""
echo "Anteprima colori Disponibilità (dry-run)..."
(cd "${CRM_ROOT}" && php tools/backfill-brand-color-calendario.php \
  --only=disponibilita --force-color --dry-run --verbose)

echo ""
echo "Aggiornamento colori Disponibilità (solo campo color, skipHooks)..."
(cd "${CRM_ROOT}" && php tools/backfill-brand-color-calendario.php \
  --only=disponibilita --force-color --verbose)

echo ""
echo "Fatto. Ctrl+Shift+R sul calendario."
echo "Date e nomi NON sono stati modificati — solo colori brand."
