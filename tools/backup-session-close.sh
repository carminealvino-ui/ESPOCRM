#!/usr/bin/env bash
# Backup chiusura sessione — snapshot generale custom + client + sessione backup_dev.
#
# Uso (dalla root CRM):
#   curl -fsSL ".../backup-session-close.sh" -o tools/backup-session-close.sh
#   bash tools/backup-session-close.sh [ETICHETTA]
#
# Esempio:
#   bash tools/backup-session-close.sh appuntamento-prospect-sync-ok
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
LABEL="${1:-session-close}"
TS="$(date +%Y%m%d-%H%M%S)"
SNAP_DIR="${CRM_ROOT}/backup_dev/_snapshots/${TS}_${LABEL}"

cd "${CRM_ROOT}"

echo "=== Backup chiusura sessione: ${LABEL} ==="
echo "CRM_ROOT: ${CRM_ROOT}"
echo ""

# 1) Sessione backup_dev (file del fix corrente)
if [[ -f tools/backup-dev-batch.sh ]]; then
  echo "==> 1) backup_dev sessione file fix"
  bash tools/backup-dev-batch.sh "${LABEL}" \
    --manifest tools/backup-manifests/session-close-appuntamento-prospect-sync.files
else
  echo "ATTENZIONE: tools/backup-dev-batch.sh assente — salto sessione file"
fi

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
echo ""
echo "Verifica rapida:"
echo "  grep VERSION client/custom/src/views/fields/appuntamento-parent.js"
