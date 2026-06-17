#!/usr/bin/env bash
# Layout controllo duplicati Prospect: Nome e Cognome, Indirizzo, Telefono.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/prospect-duplicate-layout-9999/tools/deploy-prospect-duplicate-layout.sh?t=$(date +%s)" -o /tmp/deploy-prospect-duplicate.sh
#   bash /tmp/deploy-prospect-duplicate.sh
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/prospect-duplicate-layout-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}" || exit 1

fetch() {
  local rel="$1"
  local dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=$(date +%s)" -o "${dest}"
  echo "OK ${rel}"
}

echo "=== Deploy layout duplicati Prospect (listSmall) ==="

fetch "custom/Espo/Custom/Resources/layouts/Prospect/listSmall.json"
fetch "custom/Espo/Custom/Resources/i18n/it_IT/Prospect.json"

php command.php rebuild
rm -rf data/cache/*
echo ""
echo "Fatto. Nel popup duplicati Prospect: Nome e Cognome, Indirizzo, Telefono."
