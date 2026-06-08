#!/usr/bin/env bash
# Google Calendar: rimuove su Not Held/delete, mantiene Ingestibile, cambio consulente.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-appuntamento-google-sync-9999/tools/deploy-fix-appuntamento-google-sync.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/fix-appuntamento-google-sync-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

FILES=(
  "custom/Espo/Custom/Services/AppuntamentoGoogleSync.php"
  "custom/Espo/Custom/Hooks/Appuntamento/GoogleCalendarSync.php"
  "custom/Espo/Custom/Hooks/Appuntamento/GoogleCalendarSyncBeforeGlobal.php"
  "custom/Espo/Custom/Hooks/Appuntamento/GoogleCalendarSyncAfterGlobal.php"
  "custom/Espo/Custom/Hooks/Appuntamento/PreventDuplicate.php"
  "custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php"
  "custom/Espo/Custom/Resources/metadata/hooks/Appuntamento.json"
)

echo "=== Fix Appuntamento Google Calendar sync → ${CRM_ROOT} ==="

for rel in "${FILES[@]}"; do
  target="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${target}")"
  curl -fsSL -o "${target}" "${BASE}/${rel}?t=$(date +%s)"
  echo "OK ${rel}"
done

if [[ -f "${CRM_ROOT}/clear_cache.php" ]]; then
  (cd "${CRM_ROOT}" && php clear_cache.php && php rebuild.php)
elif [[ -f "${CRM_ROOT}/command.php" ]]; then
  (cd "${CRM_ROOT}" && php command.php rebuild && php command.php clearCache)
fi

echo ""
echo "Fatto. Ctrl+F5."
echo "Not Held/annullato o eliminato → rimosso da Google; Ingestibile resta; cambio consulente → spostato."
echo ""
echo "Bonifica eventi orfani su Google:"
echo "  php tools/bonifica-appuntamento-google-calendar.php --dry-run"
echo "  php tools/bonifica-appuntamento-google-calendar.php --apply"
echo "Allinea agenda (rimuove annullati + push venerdì/sabato):"
echo "  php tools/bonifica-appuntamento-google-calendar.php --apply --user-id=67c93e694705fde80"
echo "Solo push mancanti:"
echo "  php tools/bonifica-appuntamento-google-calendar.php --apply --only-push --user-id=67c93e694705fde80"
