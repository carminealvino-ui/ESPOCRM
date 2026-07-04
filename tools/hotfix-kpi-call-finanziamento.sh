#!/usr/bin/env bash
# Hotfix post-deploy KPI: CallStandardTesto Espo 10 + campi finanziamento contratto.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/crm-kpi-recessi-finanziamento-9999/tools/hotfix-kpi-call-finanziamento.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/crm-kpi-recessi-finanziamento-9999"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)

cd "${CRM_ROOT}"

FILES=(
  "custom/Espo/Custom/Controllers/CallStandardTesto.php"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json"
  "custom/Espo/Custom/Resources/metadata/logicDefs/Quote.json"
  "custom/Espo/Custom/Resources/layouts/Quote/detail.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Quote.json"
  "tools/backfill-quote-finanziamento-da-opportunita.php"
)

echo "=== Hotfix KPI + finanziamento (${BRANCH}) ==="
for rel in "${FILES[@]}"; do
  dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=${STAMP}" -o "${dest}"
  echo "OK ${rel}"
done

php clear_cache.php
php rebuild.php
php tools/backfill-quote-finanziamento-da-opportunita.php
php tools/verify-crm-kpi-deploy.php || true

echo ""
echo "Fatto. Ctrl+Shift+R su CRM e dashboard KPI."
