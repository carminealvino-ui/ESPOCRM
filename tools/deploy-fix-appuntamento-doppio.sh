#!/usr/bin/env bash
# Fix doppio appuntamento (ghost Google Calendar + stesso slot/prospect).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-appuntamento-doppio-9999/tools/deploy-fix-appuntamento-doppio.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/fix-appuntamento-doppio-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

FILES=(
  "custom/Espo/Custom/Hooks/Appuntamento/PreventDuplicate.php"
  "custom/Espo/Custom/Resources/metadata/hooks/Appuntamento.json"
  "custom/Espo/Custom/Services/Appuntamento.php"
)

echo "=== Fix doppio Appuntamento → ${CRM_ROOT} ==="

for rel in "${FILES[@]}"; do
  target="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${target}")"
  curl -fsSL -o "${target}" "${BASE}/${rel}?t=$(date +%s)"
  echo "OK ${rel}"
done

echo ""
echo "Verifica registrazione hook:"
grep -A2 preventDuplicate "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/hooks/Appuntamento.json" || true

if [[ -f "${CRM_ROOT}/clear_cache.php" ]]; then
  (cd "${CRM_ROOT}" && php clear_cache.php && php rebuild.php)
elif [[ -f "${CRM_ROOT}/command.php" ]]; then
  (cd "${CRM_ROOT}" && php command.php rebuild && php command.php clearCache)
fi

echo ""
echo "Fatto. Ctrl+F5 nel browser."
echo "I nuovi duplicati vengono bloccati; i ghost esistenti vengono rimossi al prossimo save dell'appuntamento reale."
