#!/usr/bin/env bash
# KPI: esito "Appuntamento non in agenda" conteggiato come annullato.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/crm-kpi-esito-non-in-agenda-9999/tools/deploy-crm-kpi-esito-non-in-agenda.sh?t=$(date +%s)" | bash
#   php clear_cache.php && php rebuild.php

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/crm-kpi-esito-non-in-agenda-9999"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)
LOCAL_BACKUP="${CRM_ROOT}/backup/crm-kpi-esito-non-in-agenda/server-${STAMP}"

REL="custom/Espo/Custom/Services/CrmKpi/CrmKpiService.php"
SRC="${CRM_ROOT}/${REL}"

echo "=== Backup ${REL} ==="
mkdir -p "${LOCAL_BACKUP}/$(dirname "${REL}")"
if [[ -f "${SRC}" ]]; then
  cp -a "${SRC}" "${LOCAL_BACKUP}/${REL}"
  echo "BACKUP ${REL}"
fi

echo "=== Download da ${BRANCH} ==="
mkdir -p "$(dirname "${SRC}")"
curl -fsSL "${BASE}/${REL}?t=${STAMP}" -o "${SRC}"
echo "OK ${REL}"

echo ""
echo "=== Deploy completato ==="
echo "Poi: cd ${CRM_ROOT} && php clear_cache.php && php rebuild.php"
