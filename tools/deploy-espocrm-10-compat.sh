#!/usr/bin/env bash
# Compatibilità EspoCRM 10: migrazione controller/hook da classi Base rimosse in v10.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-espocrm-10-compat-9999/tools/deploy-espocrm-10-compat.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/fix-espocrm-10-compat-9999"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)

FILES=(
  "custom/Espo/Custom/Controllers/CallStandardTesto.php"
  "custom/Espo/Custom/Hooks/Quote/BeforeSave.php"
  "custom/Espo/Custom/Hooks/Prospect/SyncPlannedAppointments.php"
)

echo "==> Deploy EspoCRM 10 compat (${BRANCH}) in ${CRM_ROOT}"

for rel in "${FILES[@]}"; do
  dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=${STAMP}" -o "${dest}"
  echo "  OK ${rel}"
done

LEGACY="${CRM_ROOT}/custom/Espo/Custom/Hooks/Provvigione/BeforeSaveLegacy.php"
if [[ -f "${LEGACY}" ]]; then
  rm -f "${LEGACY}"
  echo "  RIMOSSO BeforeSaveLegacy.php (sostituito da AccrualAndAmount)"
fi

cd "${CRM_ROOT}"
php command.php rebuild
php command.php clear-cache

echo "==> Deploy completato"
