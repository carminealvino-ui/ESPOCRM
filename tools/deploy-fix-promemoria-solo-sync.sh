#!/usr/bin/env bash
# Fix promemoria: sync SOLO (niente nuove Call) + provider Appuntamento.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-promemoria-solo-sync-9999/tools/deploy-fix-promemoria-solo-sync.sh?t=$(date +%s)" \
#     -o tools/deploy-fix-promemoria-solo-sync.sh
#   bash tools/deploy-fix-promemoria-solo-sync.sh
#   php tools/sync-promemoria-esistenti.php --apply

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/fix-promemoria-solo-sync-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

FILES=(
  "custom/Espo/Custom/Hooks/Provvigione/BeforeSaveLegacy.php"
  "custom/Espo/Custom/Tools/Activities/PopupNotificationsProvider.php"
  "custom/Espo/Custom/Classes/Select/Call/PrimaryFilters/ChiamateScadute.php"
  "custom/Espo/Custom/Resources/metadata/selectDefs/Call.json"
  "custom/Espo/Custom/Resources/metadata/clientDefs/Call.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Call.json"
  "tools/sync-promemoria-esistenti.php"
)

echo "=== Fix promemoria solo-sync → ${CRM_ROOT} ==="
cd "${CRM_ROOT}"

for rel in "${FILES[@]}"; do
  target="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${target}")"
  curl -fsSL -o "${target}" "${BASE}/${rel}?t=$(date +%s)"
  echo "OK ${rel}"
done

grep -q 'findPastPlannedAppuntamentoItems' \
  "${CRM_ROOT}/custom/Espo/Custom/Tools/Activities/PopupNotificationsProvider.php" || {
  echo "ERRORE: provider senza findPastPlannedAppuntamentoItems" >&2
  exit 1
}

grep -q 'NESSUNA nuova Call' "${CRM_ROOT}/tools/sync-promemoria-esistenti.php" || {
  echo "ERRORE: sync script non aggiornato" >&2
  exit 1
}

if [[ -f clear_cache.php ]]; then
  php clear_cache.php || true
fi

echo ""
echo "=== Deploy OK ==="
echo "Anteprima: php tools/sync-promemoria-esistenti.php"
echo "Applica:   php tools/sync-promemoria-esistenti.php --apply"
echo "Poi Ctrl+F5"
