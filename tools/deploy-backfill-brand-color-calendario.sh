#!/usr/bin/env bash
# Deploy rapido: script backfill brand/colore calendario + servizio PHP.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/disponibilita-da-calendario-lavorativo-9999/tools/deploy-backfill-brand-color-calendario.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/disponibilita-da-calendario-lavorativo-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

FILES=(
  "custom/Espo/Custom/Services/BrandCalendarColorBackfill.php"
  "custom/Espo/Custom/Actions/Disponibilita/BackfillBrandColorCalendario.php"
  "custom/Espo/Custom/Controllers/Disponibilita.php"
  "custom/Espo/Custom/Resources/metadata/app/actions.json"
  "tools/backfill-brand-color-calendario.php"
  "tools/data/brand-calendar-colors.example.json"
)

echo "=== Deploy backfill brand/colore → ${CRM_ROOT} (branch ${BRANCH}) ==="

for rel in "${FILES[@]}"; do
  target="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${target}")"
  curl -fsSL -o "${target}" "${BASE}/${rel}?t=$(date +%s)"
  echo "OK ${rel}"
done

chmod +x "${CRM_ROOT}/tools/backfill-brand-color-calendario.php" 2>/dev/null || true

if [[ -f "${CRM_ROOT}/clear_cache.php" ]]; then
  (cd "${CRM_ROOT}" && php clear_cache.php)
elif [[ -f "${CRM_ROOT}/command.php" ]]; then
  (cd "${CRM_ROOT}" && php command.php clearCache)
fi

echo ""
echo "Fatto. Prova:"
echo "  cd ${CRM_ROOT}"
echo "  php tools/backfill-brand-color-calendario.php --dry-run"
echo "  cp tools/data/brand-calendar-colors.example.json tools/data/brand-calendar-colors.json"
echo "  php tools/backfill-brand-color-calendario.php --apply-default-colors"
echo "  php tools/backfill-brand-color-calendario.php"
