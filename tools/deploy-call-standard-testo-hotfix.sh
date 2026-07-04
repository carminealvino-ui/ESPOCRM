#!/usr/bin/env bash
# Hotfix Espo 10: CallStandardTesto senza Espo\Core\Controllers\Base (rimosso in v10).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/crm-kpi-recessi-finanziamento-9999/tools/deploy-call-standard-testo-hotfix.sh?t=$(date +%s)" | bash
#   php clear_cache.php

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/crm-kpi-recessi-finanziamento-9999"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)

FILES=(
  "custom/Espo/Custom/Services/CallStandardTesto.php"
  "custom/Espo/Custom/Controllers/CallStandardTesto.php"
  "custom/Espo/Custom/Hooks/Call/PersistStandardTesto.php"
)

for rel in "${FILES[@]}"; do
  dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=${STAMP}" -o "${dest}"
  echo "OK ${rel}"
done

echo ""
echo "Poi: cd ${CRM_ROOT} && php clear_cache.php"
