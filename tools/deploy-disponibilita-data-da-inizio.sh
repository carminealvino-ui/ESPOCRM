#!/usr/bin/env bash
# Deploy: Data Disponibilità = data di dateStart (solo data, senza orario).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/disponibilita-data-da-inizio-9999/tools/deploy-disponibilita-data-da-inizio.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/disponibilita-data-da-inizio-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

FILES=(
  "custom/Espo/Custom/Hooks/Disponibilita/SetName.php"
  "custom/Espo/Custom/Resources/metadata/hooks/Disponibilita.json"
  "custom/Espo/Custom/Resources/metadata/formula/Disponibilita.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Disponibilita.json"
  "custom/Espo/Custom/Resources/client/custom-views/disponibilita/create.js"
  "tools/backfill-disponibilita-data-da-inizio.php"
)

for rel in "${FILES[@]}"; do
  target="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${target}")"
  curl -fsSL -o "${target}" "${BASE}/${rel}?t=$(date +%s)"
  echo "OK ${target}"
done

if [[ -f "${CRM_ROOT}/clear_cache.php" ]]; then
  (cd "${CRM_ROOT}" && php clear_cache.php && php rebuild.php)
elif [[ -f "${CRM_ROOT}/command.php" ]]; then
  (cd "${CRM_ROOT}" && php command.php rebuild && php command.php clearCache)
fi

echo ""
echo "Diagnostica (campione DB):"
(cd "${CRM_ROOT}" && php tools/backfill-disponibilita-data-da-inizio.php --sample | head -20)

echo ""
echo "Backfill record esistenti (dry-run):"
(cd "${CRM_ROOT}" && php tools/backfill-disponibilita-data-da-inizio.php --dry-run --verbose | tail -10)

echo ""
echo "Per applicare il backfill:"
echo "  cd ${CRM_ROOT} && php tools/backfill-disponibilita-data-da-inizio.php --verbose"
echo ""
echo "Fatto. Ctrl+F5 nel browser."
