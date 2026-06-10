#!/usr/bin/env bash
# UI Appuntamento: sync Fornitore/Brand/Categoria da Prospect.
#
# PASSO 0 — backup:
#   bash tools/backup-dev-batch.sh prospect-form-ui --manifest tools/backup-manifests/prospect-form-ui.files
#
# Deploy (dalla root CRM, es. ~/public_html/crm/mec-group):
#   bash tools/deploy-prospect-appuntamento-form-ui.sh
#   bash tools/deploy-prospect-appuntamento-form-ui.sh cursor/fix-appuntamento-prospect-sync-9999
set -euo pipefail

if [[ "${1:-}" == cursor/* ]]; then
  BRANCH="$1"
  CRM_ROOT="${2:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
else
  CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
  BRANCH="${2:-cursor/fix-appuntamento-prospect-sync-9999}"
fi

REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
FIX_TAG="prospect-form-ui"
VERSION_MARKER="VERSION = '1.2.0'"

FILES=(
  "custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/metadata/clientDefs/Calendar.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json"
  "client/custom/src/helpers/appuntamento-prospect-sync.js"
  "client/custom/src/views/appuntamento/record/edit.js"
  "client/custom/src/views/appuntamento/record/edit-small.js"
  "client/custom/src/views/calendar/calendar.js"
  "client/custom/src/views/calendar/modals/edit.js"
  "client/custom/src/views/fields/appuntamento-parent.js"
  "client/custom/src/views/fields/fornitore-partner-cascade.js"
  "client/custom/src/views/fields/product-brand-by-partner.js"
)

echo "=== Deploy sync Prospect → Appuntamento ==="
echo "CRM_ROOT: ${CRM_ROOT}"
echo "BRANCH:   ${BRANCH}"
echo ""

has_backup() {
  local sessions="${CRM_ROOT}/backup_dev/_sessions"
  [[ -d "${sessions}" ]] || return 1
  local latest
  latest="$(find "${sessions}" -maxdepth 1 -type d -name "*_${FIX_TAG}" 2>/dev/null | sort -r | head -1)"
  [[ -n "${latest}" && -f "${latest}/manifest.txt" && -f "${latest}/files.list" ]]
}

if [[ "${SKIP_BACKUP_CHECK:-}" != "1" ]] && ! has_backup; then
  echo "PASSO 0 — esegui prima il backup:"
  echo "  cd ${CRM_ROOT}"
  echo "  bash tools/backup-dev-batch.sh ${FIX_TAG} --manifest tools/backup-manifests/prospect-form-ui.files"
  echo ""
  echo "Poi:"
  echo "  bash tools/deploy-prospect-appuntamento-form-ui.sh ${BRANCH}"
  exit 1
fi

if has_backup; then
  latest="$(find "${CRM_ROOT}/backup_dev/_sessions" -maxdepth 1 -type d -name "*_${FIX_TAG}" 2>/dev/null | sort -r | head -1)"
  echo "Backup: ${latest#${CRM_ROOT}/}"
fi
echo ""

client_targets() {
  local rel="$1"
  local suffix="${rel#client/custom/}"
  echo "${CRM_ROOT}/${rel}"
  echo "${CRM_ROOT}/custom/Espo/Custom/Resources/client/custom/${suffix}"
  echo "${CRM_ROOT}/custom/Espo/Custom/client/custom/${suffix}"
}

for rel in "${FILES[@]}"; do
  TMP="$(mktemp)"
  curl -fsSL -o "${TMP}" "${BASE}/${rel}?t=$(date +%s)"

  if [[ "${rel}" == "client/custom/src/helpers/appuntamento-prospect-sync.js" ]]; then
    if ! grep -q "${VERSION_MARKER}" "${TMP}"; then
      echo "ERRORE: ${rel} remoto non contiene ${VERSION_MARKER}" >&2
      rm -f "${TMP}"
      exit 1
    fi
  fi

  if [[ "${rel}" == client/custom/* ]]; then
    while IFS= read -r target; do
      mkdir -p "$(dirname "${target}")"
      cp "${TMP}" "${target}"
      echo "OK ${target#${CRM_ROOT}/}"
    done < <(client_targets "${rel}")
  else
    target="${CRM_ROOT}/${rel}"
    mkdir -p "$(dirname "${target}")"
    cp "${TMP}" "${target}"
    echo "OK ${rel}"
  fi

  rm -f "${TMP}"
done

if [[ -f "${CRM_ROOT}/clear_cache.php" ]]; then
  (cd "${CRM_ROOT}" && php clear_cache.php && php rebuild.php)
elif [[ -f "${CRM_ROOT}/command.php" ]]; then
  (cd "${CRM_ROOT}" && php command.php clearCache && php command.php rebuild)
fi

echo ""
echo "Fatto. Ctrl+Shift+R nel browser."
echo ""
echo "Verifica in console browser (F12):"
echo "  require('custom:helpers/appuntamento-prospect-sync').VERSION  => 1.2.0"
echo ""
echo "Test: Crea Appuntamento → Prospect in Relazionato a → Fornitore/Brand/Categoria compilati"
