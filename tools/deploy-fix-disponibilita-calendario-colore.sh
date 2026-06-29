#!/usr/bin/env bash
# Allinea colore calendario lavorativo (Disponibilità ricorrenti) alla palette brand.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-disponibilita-calendario-colore-9999/tools/deploy-fix-disponibilita-calendario-colore.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/fix-disponibilita-calendario-colore-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

FILES=(
  "custom/Espo/Custom/Hooks/Disponibilita/SetName.php"
  "custom/Espo/Custom/Resources/metadata/app/calendar.json"
  "client/custom/src/views/calendar/calendar.js"
  "tools/data/brand-calendar-colors.json"
)

echo "=== Fix colore calendario Disponibilità ricorrenti → ${CRM_ROOT} ==="

for rel in "${FILES[@]}"; do
  target="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${target}")"
  curl -fsSL -o "${target}" "${BASE}/${rel}?t=$(date +%s)"
  echo "OK ${rel}"
done

deploy_client_file() {
  local rel="$1"
  local tmp="$2"
  local suffix="${rel#client/custom/}"
  local target

  for target in \
    "${CRM_ROOT}/${rel}" \
    "${CRM_ROOT}/custom/Espo/Custom/Resources/client/custom/${suffix}" \
    "${CRM_ROOT}/custom/Espo/Custom/client/custom/${suffix}"; do
    mkdir -p "$(dirname "${target}")"
    cp "${tmp}" "${target}"
    echo "OK ${target#${CRM_ROOT}/}"
  done
}

TMP="$(mktemp)"
curl -fsSL -o "${TMP}" "${BASE}/client/custom/src/views/calendar/calendar.js?t=$(date +%s)"
deploy_client_file "client/custom/src/views/calendar/calendar.js" "${TMP}"
rm -f "${TMP}"

(cd "${CRM_ROOT}" && php command.php rebuild && php command.php clearCache)

echo ""
echo "Backfill colori esistenti (opzionale ma consigliato):"
echo "  cd ${CRM_ROOT}"
echo "  php tools/backfill-brand-color-calendario.php --apply-default-colors --only=disponibilita --force-color"
echo ""
echo "Fatto. Ctrl+Shift+R sul calendario."
