#!/usr/bin/env bash
# Fix 409 duplicate su POST /Prospect durante Crea Appuntamento + prefill prospect.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/invito-a-fatturare-selezione-9999/tools/deploy-fix-prospect-appuntamento-409.sh?t=$(date +%s)" | bash
#   php clear_cache.php

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/invito-a-fatturare-selezione-9999"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)
BACKUP="${CRM_ROOT}/backup_dev/Prospect/appuntamento-409-${STAMP}"

FILES=(
  "custom/Espo/Custom/Services/Prospect.php"
  "client/custom/src/helpers/appuntamento-prospect-sync.js"
  "client/custom/src/views/fields/appuntamento-parent.js"
  "client/custom/src/views/appuntamento/fields/duration.js"
  "client/custom/src/views/appuntamento/record/edit-small.js"
  "custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json"
)

echo "=== Backup ${BACKUP} ==="
mkdir -p "${BACKUP}"

for rel in "${FILES[@]}"; do
  src="${CRM_ROOT}/${rel}"
  if [[ -f "${src}" ]]; then
    mkdir -p "${BACKUP}/$(dirname "${rel}")"
    cp -a "${src}" "${BACKUP}/${rel}"
    echo "BACKUP ${rel}"
  fi
done

echo ""
echo "=== Download fix ==="
for rel in "${FILES[@]}"; do
  dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=${STAMP}" -o "${dest}"
  echo "OK ${rel}"
done

grep -q "findDuplicateProspect" "${CRM_ROOT}/custom/Espo/Custom/Services/Prospect.php"

echo ""
echo "Poi: cd ${CRM_ROOT} && php clear_cache.php && rm -rf data/cache/*"
