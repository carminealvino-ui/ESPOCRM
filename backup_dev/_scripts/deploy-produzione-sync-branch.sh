#!/bin/bash
# ========================================
# Deploy produzione — sync branch GitHub → CRM
# Uso: bash backup_dev/_scripts/deploy-produzione-sync-branch.sh
# Root CRM: ~/public_html/crm/mec-group
# Archivi completi: backup_dev/_archives/
# ========================================

set -euo pipefail

BRANCH="${DEPLOY_BRANCH:-cursor/opportunity-globallogic-9999}"
REPO="${DEPLOY_REPO:-carminealvino-ui/ESPOCRM}"
CRM_ROOT="${CRM_ROOT:-$(pwd)}"
TS="$(date +%Y-%m-%d-%H%M)"
BACKUP_DIR="${CRM_ROOT}/backup_dev/_archives"
TMP_DIR="$(mktemp -d)"

cd "${CRM_ROOT}"

mkdir -p "${BACKUP_DIR}"

echo "==> Backup custom + client/custom (${TS})"
tar -czf "${BACKUP_DIR}/backup-custom-client-${TS}.tar.gz" custom client/custom 2>/dev/null || \
  tar -czf "${BACKUP_DIR}/backup-custom-${TS}.tar.gz" custom

ARCHIVE_URL="https://github.com/${REPO}/archive/refs/heads/${BRANCH}.tar.gz"
echo "==> Download branch ${BRANCH}"
curl -fsSL "${ARCHIVE_URL}" -o "${TMP_DIR}/branch.tar.gz"

tar -xzf "${TMP_DIR}/branch.tar.gz" -C "${TMP_DIR}"
SRC="${TMP_DIR}/ESPOCRM-${BRANCH//\//-}"

if [[ ! -d "${SRC}/custom" ]]; then
  # GitHub sostituisce / con - nel nome cartella estratta
  SRC="$(find "${TMP_DIR}" -maxdepth 1 -type d -name 'ESPOCRM-*' | head -1)"
fi

if [[ ! -d "${SRC}/custom" ]]; then
  echo "ERRORE: cartella custom non trovata nell'archivio."
  exit 1
fi

echo "==> Copia custom/"
mkdir -p "${CRM_ROOT}/custom"
cp -a "${SRC}/custom/." "${CRM_ROOT}/custom/"

if [[ -d "${SRC}/client/custom" ]]; then
  mkdir -p "${CRM_ROOT}/client/custom"
  echo "==> Copia client/custom/"
  cp -a "${SRC}/client/custom/." "${CRM_ROOT}/client/custom/"
fi

echo "==> Rebuild + Clear cache"
php command.php rebuild
php command.php clear-cache

rm -rf "${TMP_DIR}"

echo ""
echo "OK. Deploy completato."
echo "Backup: ${BACKUP_DIR}/backup-custom-client-${TS}.tar.gz"
echo "Poi Ctrl+F5 nel browser."
