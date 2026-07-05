#!/usr/bin/env bash
# Wrapper: fix hook duplicati (portabile, no /dev/fd)
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL ".../tools/fix-duplicate-hooks.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/fix-appuntamento-calendario-500-9999"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)

echo "=== Download InvitoBeforeSave.php ==="
mkdir -p "${CRM_ROOT}/custom/Espo/Custom/Hooks/InvitoAFatturare"
curl -fsSL "${BASE}/custom/Espo/Custom/Hooks/InvitoAFatturare/InvitoBeforeSave.php?t=${STAMP}" \
  -o "${CRM_ROOT}/custom/Espo/Custom/Hooks/InvitoAFatturare/InvitoBeforeSave.php"
echo "OK InvitoBeforeSave.php"

curl -fsSL "${BASE}/tools/fix-duplicate-hooks.php?t=${STAMP}" \
  -o "${CRM_ROOT}/tools/fix-duplicate-hooks.php"
curl -fsSL "${BASE}/tools/diagnose-duplicate-hooks.php?t=${STAMP}" \
  -o "${CRM_ROOT}/tools/diagnose-duplicate-hooks.php"

php "${CRM_ROOT}/tools/fix-duplicate-hooks.php"
