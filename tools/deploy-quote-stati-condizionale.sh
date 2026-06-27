#!/usr/bin/env bash
# Deploy layout e logiche stato Contratto (Quote + Opportunity).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/quote-stati-condizionale-9999/tools/deploy-quote-stati-condizionale.sh?t=$(date +%s)" | bash
#   php clear_cache.php && php rebuild.php

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/quote-stati-condizionale-9999"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)

FILES=(
  "custom/Espo/Custom/Resources/layouts/Quote/detail.json"
  "custom/Espo/Custom/Resources/layouts/Opportunity/detail.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json"
  "custom/Espo/Custom/Resources/metadata/logicDefs/Quote.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Quote.json"
)

echo "=== Deploy stati Contratto/Opportunità (${BRANCH}) ==="

for rel in "${FILES[@]}"; do
  dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=${STAMP}" -o "${dest}"
  echo "OK ${rel}"
done

echo ""
echo "Poi: cd ${CRM_ROOT} && php clear_cache.php && php rebuild.php"
