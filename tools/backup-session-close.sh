#!/usr/bin/env bash
# Backup chiusura sessione — snapshot generale custom + client + sessione backup_dev.
#
# Uso (dalla root CRM):
#   bash tools/backup-session-close.sh [ETICHETTA]
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
LABEL="${1:-session-close}"
BRANCH="${BACKUP_BRANCH:-cursor/fix-appuntamento-prospect-sync-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
TS="$(date +%Y%m%d-%H%M%S)"
SNAP_DIR="${CRM_ROOT}/backup_dev/_snapshots/${TS}_${LABEL}"

# File del fix Prospect → Appuntamento v1.2.5 (lista inline, no manifest esterno)
SESSION_FILES=(
  "custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/metadata/clientDefs/Calendar.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json"
  "client/custom/src/views/fields/appuntamento-parent.js"
  "client/custom/src/views/fields/fornitore-partner-cascade.js"
  "client/custom/src/views/fields/product-brand-by-partner.js"
  "client/custom/src/views/appuntamento/record/edit.js"
  "client/custom/src/views/appuntamento/record/edit-small.js"
  "client/custom/src/views/calendar/calendar.js"
  "client/custom/src/views/calendar/modals/edit.js"
  "tools/deploy-prospect-appuntamento-form-ui.sh"
)

cd "${CRM_ROOT}"

echo "=== Backup chiusura sessione: ${LABEL} ==="
echo "CRM_ROOT: ${CRM_ROOT}"
echo ""

if [[ ! -f tools/backup-dev-batch.sh ]]; then
  echo "Download tools/backup-dev-batch.sh ..."
  mkdir -p tools
  curl -fsSL -o tools/backup-dev-batch.sh "${BASE}/tools/backup-dev-batch.sh?t=$(date +%s)"
  chmod +x tools/backup-dev-batch.sh
fi

echo "==> 1) backup_dev sessione file fix"
bash tools/backup-dev-batch.sh "${LABEL}" "${SESSION_FILES[@]}"

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
  echo "note=Backup chiusura sessione — sync Prospect Appuntamento v1.2.5"
  echo "verify_appuntamento_parent=$(grep 'VERSION' client/custom/src/views/fields/appuntamento-parent.js 2>/dev/null | head -1 || echo 'n/d')"
  echo ""
  ls -la "${SNAP_DIR}"
} > "${SNAP_DIR}/MANIFEST.txt"

echo ""
echo "=== Backup sessione completato ==="
echo "Snapshot: backup_dev/_snapshots/${TS}_${LABEL}/"
echo "Manifest: ${SNAP_DIR}/MANIFEST.txt"
