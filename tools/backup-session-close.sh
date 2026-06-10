#!/usr/bin/env bash
# Backup chiusura sessione — snapshot generale custom + client + sessione backup_dev.
#
# Uso (dalla root CRM):
#   bash tools/backup-session-close.sh [ETICHETTA]
#
# Esempio:
#   bash tools/backup-session-close.sh appuntamento-prospect-sync-ok
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
LABEL="${1:-session-close}"
BRANCH="${BACKUP_BRANCH:-cursor/fix-appuntamento-prospect-sync-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
TS="$(date +%Y%m%d-%H%M%S)"
SNAP_DIR="${CRM_ROOT}/backup_dev/_snapshots/${TS}_${LABEL}"
MANIFEST_REL="tools/backup-manifests/session-close-appuntamento-prospect-sync.files"

cd "${CRM_ROOT}"

echo "=== Backup chiusura sessione: ${LABEL} ==="
echo "CRM_ROOT: ${CRM_ROOT}"
echo ""

ensure_tool() {
  local rel="$1"
  local target="${CRM_ROOT}/${rel}"

  if [[ -f "${target}" ]]; then
    return 0
  fi

  mkdir -p "$(dirname "${target}")"
  echo "Download ${rel} ..."
  curl -fsSL -o "${target}" "${BASE}/${rel}?t=$(date +%s)"
  chmod +x "${target}" 2>/dev/null || true
}

ensure_tool "tools/backup-dev-batch.sh"
ensure_tool "${MANIFEST_REL}"

echo "==> 1) backup_dev sessione file fix"
bash tools/backup-dev-batch.sh "${LABEL}" --manifest "${MANIFEST_REL}"

echo ""
echo "==> 2) Snapshot tar custom + client/custom"
mkdir -p "${SNAP_DIR}"

if [[ -d custom ]]; then
  tar -czf "${SNAP_DIR}/custom.tar.gz" -C "${CRM_ROOT}" custom
  echo "OK ${SNAP_DIR}/custom.tar.gz ($(du -h "${SNAP_DIR}/custom.tar.gz" | cut -f1))"
fi

if [[ -d client/custom ]]; then
  tar -czf "${SNAP_DIR}/client-custom.tar.gz" -C "${CRM_ROOT}/client" custom
  echo "OK ${SNAP_DIR}/client-custom.tar.gz ($(du -h "${SNAP_DIR}/client-custom.tar.gz" | cut -f1))"
fi

{
  echo "timestamp=${TS}"
  echo "label=${LABEL}"
  echo "crm_root=${CRM_ROOT}"
  echo "branch=${BRANCH}"
  echo "note=Backup chiusura sessione — sync Prospect Appuntamento v1.2.5"
  echo "verify_appuntamento_parent=$(grep 'VERSION' client/custom/src/views/fields/appuntamento-parent.js 2>/dev/null | head -1 || echo 'n/d')"
  echo ""
  ls -la "${SNAP_DIR}"
} > "${SNAP_DIR}/MANIFEST.txt"

echo ""
echo "=== Backup sessione completato ==="
echo "Snapshot: backup_dev/_snapshots/${TS}_${LABEL}/"
echo "Manifest: ${SNAP_DIR}/MANIFEST.txt"
echo ""
echo "Verifica rapida:"
echo "  grep VERSION client/custom/src/views/fields/appuntamento-parent.js"
