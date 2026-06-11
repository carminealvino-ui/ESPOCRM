#!/usr/bin/env bash
# UI Opportunità: sync Fonte Lead / Partner / Brand / Categoria / Listino da Appuntamento.
#
# Deploy:
#   bash tools/deploy-opportunity-appuntamento-sync.sh cursor/opportunity-from-appuntamento-sync-9999
set -euo pipefail

if [[ "${1:-}" == cursor/* ]]; then
  BRANCH="$1"
  CRM_ROOT="${2:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
else
  CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
  BRANCH="${2:-cursor/opportunity-from-appuntamento-sync-9999}"
fi

REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
FIX_TAG="opportunity-appuntamento-sync"
VERSION_MARKER="VERSION = '1.0.4'"
HOOK_VERSION="2.2.7"

FILES=(
  "custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/metadata/clientDefs/Opportunity.json"
  "custom/Espo/Custom/Hooks/Opportunity/GlobalLogic.php"
  "client/custom/src/views/appuntamento/record/detail.js"
  "client/custom/src/views/opportunity/helpers/appuntamento-sync.js"
  "client/custom/src/views/opportunity/record/edit-small.js"
  "client/custom/src/views/opportunity/record/edit.js"
)

echo "=== Deploy sync Appuntamento → Opportunità v1.0.3 ==="
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
  echo "  bash tools/backup-dev-batch.sh ${FIX_TAG} --manifest tools/backup-manifests/opportunity-appuntamento-sync.files"
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

  if [[ "${rel}" == *appuntamento-sync.js ]]; then
    if ! grep -q "${VERSION_MARKER}" "${TMP}"; then
      echo "ERRORE: ${rel} non contiene ${VERSION_MARKER}" >&2
      rm -f "${TMP}"
      exit 1
    fi
  fi

  if [[ "${rel}" == custom/Espo/Custom/Hooks/Opportunity/GlobalLogic.php ]]; then
    if ! grep -q "VERSIONE: ${HOOK_VERSION}" "${TMP}"; then
      echo "ERRORE: GlobalLogic.php non è versione ${HOOK_VERSION}" >&2
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

VERIFY="${CRM_ROOT}/client/custom/src/views/opportunity/helpers/appuntamento-sync.js"
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
echo "Sync attivo su Crea Opportunità (appuntamento-sync.js v1.0.3)."
