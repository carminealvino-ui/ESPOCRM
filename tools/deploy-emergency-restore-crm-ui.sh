#!/usr/bin/env bash
# Ripristino UI CRM se pagina bianca (rimuove calculationHandler Quote).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL ".../cursor/quote-prezzi-iva-inclusa-9999/tools/deploy-emergency-restore-crm-ui.sh" -o /tmp/restore-crm.sh
#   bash /tmp/restore-crm.sh
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/quote-prezzi-iva-inclusa-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}" || exit 1

curl -fsSL "${BASE}/custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json?t=$(date +%s)" \
  -o "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json"

rm -f "${CRM_ROOT}/client/custom/src/handlers/quote/calculation-handler.js"

php command.php rebuild
rm -rf data/cache/*
echo "OK: clientDefs Quote senza calculationHandler. Ricarica il browser (Ctrl+F5)."
