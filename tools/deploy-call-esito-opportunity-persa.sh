#!/usr/bin/env bash
# Call esito Non interessato → opportunità persa + appuntamento non più Pending.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/call-esito-opportunity-persa-9999/tools/deploy-call-esito-opportunity-persa.sh?t=$(date +%s)" | bash
#   php clear_cache.php && php rebuild.php

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/call-esito-opportunity-persa-9999"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)
LOCAL_BACKUP="${CRM_ROOT}/backup/call-esito-opportunity-persa/server-${STAMP}"

REL="custom/Espo/Custom/Hooks/Call/SyncOpportunityFromEsito.php"
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
