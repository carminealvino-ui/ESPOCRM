#!/usr/bin/env bash
# Deploy script bonifica Google Calendar + servizio sync aggiornato.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-appuntamento-google-sync-9999/tools/deploy-bonifica-appuntamento-google-calendar.sh?t=$(date +%s)" | bash
#
# Poi dry-run:
#   php tools/bonifica-appuntamento-google-calendar.php --dry-run
# Apply:
#   php tools/bonifica-appuntamento-google-calendar.php --apply

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/fix-appuntamento-google-sync-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

FILES=(
  "custom/Espo/Custom/Services/AppuntamentoGoogleSync.php"
  "tools/bonifica-appuntamento-google-calendar.php"
)

echo "=== Deploy bonifica Google Calendar → ${CRM_ROOT} ==="

for rel in "${FILES[@]}"; do
  target="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${target}")"
  curl -fsSL -o "${target}" "${BASE}/${rel}?t=$(date +%s)"
  echo "OK ${rel}"
done

chmod +x "${CRM_ROOT}/tools/bonifica-appuntamento-google-calendar.php" 2>/dev/null || true

if [[ -f "${CRM_ROOT}/clear_cache.php" ]]; then
  (cd "${CRM_ROOT}" && php clear_cache.php)
fi

echo ""
echo "1) Anteprima:  php tools/bonifica-appuntamento-google-calendar.php --dry-run"
echo "2) Esegui:     php tools/bonifica-appuntamento-google-calendar.php --apply"
echo "   Solo ingestibili admin→Carmine:"
echo "   php tools/bonifica-appuntamento-google-calendar.php --apply --only-ingestibili"
echo "   Push mancanti su Google (sabato/futuri):"
echo "   php tools/bonifica-appuntamento-google-calendar.php --apply --only-push --user-id=67c93e694705fde80"
