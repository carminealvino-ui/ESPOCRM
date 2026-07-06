#!/usr/bin/env bash
# Hotfix: KPI 500 dopo deploy fase A — Appuntamento.php con getContainer() (Espo 10).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/allinea-post-2-luglio-9999/tools/deploy-fix-kpi-appuntamento-espo10.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/fix-espocrm-10-compat-9999"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)
BACKUP="${CRM_ROOT}/backup_dev/KPI/espo10-appuntamento-${STAMP}"

mkdir -p "${BACKUP}"
src="${CRM_ROOT}/custom/Espo/Custom/Controllers/Appuntamento.php"
if [[ -f "${src}" ]]; then
  cp -a "${src}" "${BACKUP}/Appuntamento.php"
  echo "BACKUP Appuntamento.php"
fi

curl -fsSL "${BASE}/custom/Espo/Custom/Controllers/Appuntamento.php?t=${STAMP}" \
  -o "${src}"
echo "OK Appuntamento.php (Espo 10)"

grep -q 'injectableFactory' "${src}"
! grep -q 'getContainer' "${src}"

cd "${CRM_ROOT}"
php clear_cache.php
php rebuild.php
rm -rf data/cache/*

echo "Fatto. Ricarica tab KPI (Ctrl+Shift+R)."
