#!/usr/bin/env bash
# Criticità KPI: 4 box su una riga (rimuove collapse 2x2 sotto 1200px nel dashlet).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/allinea-post-2-luglio-9999/tools/deploy-fix-kpi-criticita-4col.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/allinea-post-2-luglio-9999"
REPO="carminealvino-ui/ESPOCRM"
STAMP=$(date +%Y%m%d-%H%M%S)
REL="client/custom/css/crm-kpi-dashlet.css"
BACKUP="${CRM_ROOT}/backup_dev/KPI/criticita-4col-${STAMP}"

mkdir -p "${BACKUP}"
[[ -f "${CRM_ROOT}/${REL}" ]] && cp -a "${CRM_ROOT}/${REL}" "${BACKUP}/"

curl -fsSL "https://raw.githubusercontent.com/${REPO}/${BRANCH}/${REL}?t=${STAMP}" \
  -o "${CRM_ROOT}/${REL}"

grep -q 'repeat(4, minmax(0, 1fr))' "${CRM_ROOT}/${REL}"
! grep -A20 'max-width: 1200px' "${CRM_ROOT}/${REL}" | grep -q 'criticita-row'

cd "${CRM_ROOT}"
php clear_cache.php
rm -rf data/cache/*

echo "OK Criticità 4 colonne. Ctrl+Shift+R sulla dashboard KPI."
