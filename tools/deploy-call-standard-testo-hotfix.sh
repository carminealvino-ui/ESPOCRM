#!/usr/bin/env bash
# Hotfix URGENTE: CRM giù con "Class Espo\Core\Controllers\Base not found"
# in CallStandardTesto.php (incompatibile Espo 10).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/invito-a-fatturare-selezione-9999/tools/deploy-call-standard-testo-hotfix.sh?t=$(date +%s)" | bash
#   php clear_cache.php

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/invito-a-fatturare-selezione-9999"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)
BACKUP="${CRM_ROOT}/backup_dev/CallStandardTesto/hotfix-${STAMP}"

echo "=== Backup in ${BACKUP} ==="
mkdir -p "${BACKUP}"

for rel in \
  "custom/Espo/Custom/Controllers/CallStandardTesto.php" \
  "custom/Espo/Custom/Services/CallStandardTesto.php" \
  "custom/Espo/Custom/Hooks/Call/PersistStandardTesto.php"
do
  src="${CRM_ROOT}/${rel}"
  if [[ -f "${src}" ]]; then
    mkdir -p "${BACKUP}/$(dirname "${rel}")"
    cp -a "${src}" "${BACKUP}/${rel}"
    echo "BACKUP ${rel}"
  fi
done

echo ""
echo "=== Download fix Espo 10 ==="
for rel in \
  "custom/Espo/Custom/Controllers/CallStandardTesto.php" \
  "custom/Espo/Custom/Services/CallStandardTesto.php" \
  "custom/Espo/Custom/Hooks/Call/PersistStandardTesto.php"
do
  dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=${STAMP}" -o "${dest}"
  echo "OK ${rel}"
done

echo ""
echo "Poi: cd ${CRM_ROOT} && php clear_cache.php"
echo "Rollback: cp -a ${BACKUP}/* ${CRM_ROOT}/"
