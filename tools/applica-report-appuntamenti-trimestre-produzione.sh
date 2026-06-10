#!/usr/bin/env bash
# Duplica report Appuntamenti Mese → Ultimo Trimestre e allinea dashboard.
# Uso: cd ~/public_html/crm/mec-group && bash tools/applica-report-appuntamenti-trimestre-produzione.sh

set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$(pwd)}"
BRANCH="${BRANCH:-main}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

curl -fsSL -o "${CRM_ROOT}/tools/duplica-report-appuntamenti-trimestre.php" \
  "${BASE}/tools/duplica-report-appuntamenti-trimestre.php"

cd "${CRM_ROOT}"
php tools/duplica-report-appuntamenti-trimestre.php --force

if [[ -f clear_cache.php ]]; then
  php clear_cache.php
  php rebuild.php
fi

echo ""
echo "Fatto. Ctrl+F5 → tab Appuntamenti Ultimo Trimestre."
