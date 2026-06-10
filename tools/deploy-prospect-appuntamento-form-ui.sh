#!/usr/bin/env bash
# UI Appuntamento: sync Fornitore/Brand/Categoria da Prospect (campo parent autonomo).
#
# Deploy:
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
VERSION_MARKER="VERSION = '1.2.5'"

FILES=(
  "custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/metadata/clientDefs/Calendar.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json"
  "client/custom/src/views/appuntamento/record/edit.js"
  "client/custom/src/views/appuntamento/record/edit-small.js"
  "client/custom/src/views/calendar/calendar.js"
  "client/custom/src/views/calendar/modals/edit.js"
  "client/custom/src/views/fields/appuntamento-parent.js"
  "client/custom/src/views/fields/fornitore-partner-cascade.js"
  "client/custom/src/views/fields/product-brand-by-partner.js"
)

echo "=== Deploy sync Prospect → Appuntamento v1.2.5 ==="
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
  echo "PASSO 0 — backup:"
  echo "  bash tools/backup-dev-batch.sh ${FIX_TAG} --manifest tools/backup-manifests/prospect-form-ui.files"
  exit 1
fi

if has_backup; then
  latest="$(find "${CRM_ROOT}/backup_dev/_sessions" -maxdepth 1 -type d -name "*_${FIX_TAG}" 2>/dev/null | sort -r | head -1)"
  echo "Backup: ${latest#${CRM_ROOT}/}"
fi
echo ""

fix_permissions() {
  local path="$1"
  chmod 644 "${path}" 2>/dev/null || true
  local dir
  dir="$(dirname "${path}")"
  while [[ "${dir}" == "${CRM_ROOT}"/* ]]; do
    chmod 755 "${dir}" 2>/dev/null || true
    [[ "${dir}" == "${CRM_ROOT}/client" ]] && break
    dir="$(dirname "${dir}")"
  done
}

deploy_client_file() {
  local rel="$1"
  local tmp="$2"
  local suffix="${rel#client/custom/}"
  local target

  for target in \
    "${CRM_ROOT}/${rel}" \
    "${CRM_ROOT}/custom/Espo/Custom/Resources/client/custom/${suffix}" \
    "${CRM_ROOT}/custom/Espo/Custom/client/custom/${suffix}"; do
    mkdir -p "$(dirname "${target}")"
    cp "${tmp}" "${target}"
    fix_permissions "${target}"
    echo "OK ${target#${CRM_ROOT}/}"
  done
}

for rel in "${FILES[@]}"; do
  TMP="$(mktemp)"
  curl -fsSL -o "${TMP}" "${BASE}/${rel}?t=$(date +%s)"

  if [[ "${rel}" == *appuntamento-parent.js ]]; then
    if ! grep -q "${VERSION_MARKER}" "${TMP}"; then
      echo "ERRORE: ${rel} non contiene ${VERSION_MARKER}" >&2
      rm -f "${TMP}"
      exit 1
    fi
  fi

  if [[ "${rel}" == client/custom/* ]]; then
    deploy_client_file "${rel}" "${TMP}"
  else
    target="${CRM_ROOT}/${rel}"
    mkdir -p "$(dirname "${target}")"
    cp "${TMP}" "${target}"
    fix_permissions "${target}"
    echo "OK ${rel}"
  fi

  rm -f "${TMP}"
done

# Rimuovi prospect-sync.js (causava 403 e blocco calendario)
for stale in \
  "${CRM_ROOT}/client/custom/src/views/appuntamento/prospect-sync.js" \
  "${CRM_ROOT}/client/custom/src/helpers/appuntamento-prospect-sync.js" \
  "${CRM_ROOT}/client/custom/src/views/appuntamento/helpers/prospect-sync.js" \
  "${CRM_ROOT}/custom/Espo/Custom/Resources/client/custom/src/views/appuntamento/prospect-sync.js" \
  "${CRM_ROOT}/custom/Espo/Custom/client/custom/src/views/appuntamento/prospect-sync.js"; do
  if [[ -f "${stale}" ]]; then
    rm -f "${stale}"
    echo "RM ${stale#${CRM_ROOT}/}"
  fi
done

VERIFY="${CRM_ROOT}/client/custom/src/views/fields/appuntamento-parent.js"
if [[ ! -f "${VERIFY}" ]]; then
  echo "ERRORE: manca ${VERIFY}" >&2
  exit 1
fi

echo ""
echo "Verifica: $(grep 'VERSION =' "${VERIFY}" | head -1)"

if [[ -f "${CRM_ROOT}/clear_cache.php" ]]; then
  (cd "${CRM_ROOT}" && php clear_cache.php && php rebuild.php)
elif [[ -f "${CRM_ROOT}/command.php" ]]; then
  (cd "${CRM_ROOT}" && php command.php clearCache && php command.php rebuild)
fi

echo ""
echo "Fatto. Ctrl+Shift+R."
echo "Sync attivo su campo Relazionato a (appuntamento-parent.js v1.2.5)."
