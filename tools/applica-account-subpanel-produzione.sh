#!/usr/bin/env bash
# Subpanel Appuntamenti + Contratti (Quote) su scheda Cliente (Account).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/account-subpanel-appuntamenti-contratti-9999/tools/applica-account-subpanel-produzione.sh?t=$(date +%s)" -o /tmp/applica-account.sh
#   bash /tmp/applica-account.sh
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/account-subpanel-appuntamenti-contratti-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}" || exit 1

mkdir -p "${CRM_ROOT}/tools"
for t in backup-account-layouts.sh backfill-appuntamento-account-link.php; do
  curl -fsSL "${BASE}/tools/${t}?t=$(date +%s)" -o "${CRM_ROOT}/tools/${t}"
  chmod +x "${CRM_ROOT}/tools/${t}" 2>/dev/null || true
done

bash "${CRM_ROOT}/tools/backup-account-layouts.sh"

fetch() {
  local rel="$1"
  local dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=$(date +%s)" -o "${dest}"
  echo "OK ${rel}"
}

echo "=== Account: subpanel Appuntamenti + Contratti ==="

fetch "custom/Espo/Custom/Resources/layouts/Account/bottomPanelsDetail.json"
fetch "custom/Espo/Custom/Resources/metadata/entityDefs/Account.json"
fetch "custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json"
fetch "custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php"

php command.php rebuild
rm -rf data/cache/*

echo ""
echo "Backfill collegamenti appuntamento → cliente (opzionale):"
echo "  php tools/backfill-appuntamento-account-link.php --dry-run"
echo "  php tools/backfill-appuntamento-account-link.php --account-id=ID_CLIENTE"
echo ""
echo "Ctrl+Shift+R sulla scheda Cliente."
