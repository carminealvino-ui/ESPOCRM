#!/usr/bin/env bash
# Solo client KPI (UI percentuali-lordi) — non tocca PHP né metadata contratto.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/crm-kpi-recessi-finanziamento-9999/tools/deploy-kpi-ui-only.sh?t=$(date +%s)" | bash
#   php clear_cache.php && php rebuild.php
#   php tools/verify-crm-kpi-deploy.php

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/crm-kpi-recessi-finanziamento-9999"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)

FILES=(
  "client/custom/css/crm-kpi-dashlet.css"
  "client/custom/res/templates/dashlets/crm-kpi.tpl"
  "client/custom/src/views/dashlets/crm-kpi.js"
  "client/custom/src/views/dashlets/options/crm-kpi.js"
  "tools/verify-crm-kpi-deploy.php"
)

echo "=== Deploy UI KPI (solo client) ==="
for rel in "${FILES[@]}"; do
  dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=${STAMP}" -o "${dest}"
  echo "OK ${rel}"
done

echo ""
echo "Poi:"
echo "  cd ${CRM_ROOT}"
echo "  php clear_cache.php && php rebuild.php"
echo "  php tools/verify-crm-kpi-deploy.php"
echo "Browser: Ctrl+Shift+R (o finestra anonima)"
echo ""
echo "Etichette attese: Lordi · Recessi · Netti (NON 'Contratti lordi' / '100% base')"
