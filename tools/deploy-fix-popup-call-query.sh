#!/usr/bin/env bash
# Fix SQL 42S22: PopupNotificationsProvider non interroga date_start_date su Call.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-popup-call-query-9999/tools/deploy-fix-popup-call-query.sh?t=$(date +%s)" -o /tmp/deploy-fix-popup-call.sh
#   bash /tmp/deploy-fix-popup-call.sh
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/fix-popup-call-query-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}" || exit 1

fetch() {
  local rel="$1"
  local dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=$(date +%s)" -o "${dest}"
  echo "OK ${rel}"
}

echo "=== Deploy fix PopupNotificationsProvider (Call senza date_start_date) ==="

fetch "custom/Espo/Custom/Tools/Activities/PopupNotificationsProvider.php"

php command.php rebuild
rm -rf data/cache/*
echo ""
echo "Fatto. Verificare che nel log non compaiano più errori 42S22 su Call."
echo "Poi riprovare Crea Opportunità (Ctrl+Shift+R sul browser)."
