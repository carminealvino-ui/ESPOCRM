#!/usr/bin/env bash
# UI form Appuntamento: sync Fornitore/Brand/Categoria da Prospect + durata 1h30.
#
# PASSO 0 — backup obbligatorio:
#   bash tools/backup-dev-batch.sh prospect-form-ui --manifest tools/backup-manifests/prospect-form-ui.files
#
# Deploy:
#   curl -fsSL ".../deploy-prospect-appuntamento-form-ui.sh" -o tools/deploy-prospect-appuntamento-form-ui.sh
#   bash tools/deploy-prospect-appuntamento-form-ui.sh cursor/fix-appuntamento-prospect-sync-9999
set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/fix-appuntamento-prospect-sync-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
FIX_TAG="prospect-form-ui"

FILES=(
  "custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/metadata/clientDefs/Calendar.json"
  "client/custom/src/helpers/appuntamento-prospect-sync.js"
  "client/custom/src/views/appuntamento/record/edit.js"
  "client/custom/src/views/appuntamento/record/edit-small.js"
  "client/custom/src/views/calendar/calendar.js"
  "client/custom/src/views/calendar/modals/edit.js"
)

echo "=== Deploy sync Prospect → Appuntamento UI → ${CRM_ROOT} (branch ${BRANCH}) ==="

has_backup() {
  local sessions="${CRM_ROOT}/backup_dev/_sessions"
  [[ -d "${sessions}" ]] || return 1
  local latest
  latest="$(find "${sessions}" -maxdepth 1 -type d -name "*_${FIX_TAG}" 2>/dev/null | sort -r | head -1)"
  [[ -n "${latest}" && -f "${latest}/manifest.txt" && -f "${latest}/files.list" ]]
}

if [[ "${SKIP_BACKUP_CHECK:-}" != "1" ]] && ! has_backup; then
  echo ""
  echo "PASSO 0 — esegui prima il backup in backup_dev/:"
  echo "  cd ${CRM_ROOT}"
  echo "  bash tools/backup-dev-batch.sh ${FIX_TAG} --manifest tools/backup-manifests/prospect-form-ui.files"
  echo ""
  echo "Poi:"
  echo "  bash tools/deploy-prospect-appuntamento-form-ui.sh ${BRANCH}"
  exit 1
fi

if has_backup; then
  latest="$(find "${CRM_ROOT}/backup_dev/_sessions" -maxdepth 1 -type d -name "*_${FIX_TAG}" 2>/dev/null | sort -r | head -1)"
  echo "Backup rilevato: ${latest#${CRM_ROOT}/}"
fi
echo ""

for rel in "${FILES[@]}"; do
  target="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${target}")"
  curl -fsSL -o "${target}" "${BASE}/${rel}?t=$(date +%s)"
  echo "OK ${rel}"
done

if [[ -f "${CRM_ROOT}/clear_cache.php" ]]; then
  (cd "${CRM_ROOT}" && php clear_cache.php && php rebuild.php)
elif [[ -f "${CRM_ROOT}/command.php" ]]; then
  (cd "${CRM_ROOT}" && php command.php clearCache && php command.php rebuild)
fi

echo ""
echo "Fatto. Ctrl+F5 nel browser."
echo ""
echo "Verifica:"
echo "  1) Crea Appuntamento → seleziona Prospect in Relazionato a"
echo "  2) Fornitore / Brand / Categoria si compilano automaticamente"
echo "  3) Campo Prospect nel pannello laterale compilato"
echo "  4) Durata nuovo appuntamento = 1h 30m"
