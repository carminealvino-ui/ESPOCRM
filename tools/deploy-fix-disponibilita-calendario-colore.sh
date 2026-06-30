#!/usr/bin/env bash
# Calendario lavorativo: fascia bianca da Disponibilità (senza colore brand).
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
  "tools/backfill-disponibilita-data-da-inizio.php"
  "tools/fix-disponibilita-calendario-display.php"
  "tools/purge-disponibilita-orfane.php"
  "tools/report-disponibilita-settimana.php"
)

echo "=== Fix disponibilità calendario (bianco, no brand) → ${CRM_ROOT} ==="

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

grep -q "buildDisponibilitaEvents" "${CRM_ROOT}/client/custom/src/views/calendar/calendar.js" || {
  echo "ERRORE: calendar.js non aggiornato" >&2
  exit 1
}

(cd "${CRM_ROOT}" && php command.php rebuild && php command.php clearCache)

echo ""
echo "Allineamento date/orari disponibilità..."
(cd "${CRM_ROOT}" && php tools/backfill-disponibilita-data-da-inizio.php)

echo ""
echo "Ripristino etichette, isAllDay e colori..."
(cd "${CRM_ROOT}" && php tools/fix-disponibilita-calendario-display.php)

echo ""
echo "Eliminazione 14 disponibilità orfane (senza data/orario)..."
(cd "${CRM_ROOT}" && php tools/purge-disponibilita-orfane.php)

echo ""
echo "Report settimana 29/06 - 05/07:"
(cd "${CRM_ROOT}" && php tools/report-disponibilita-settimana.php --from=2026-06-29 --to=2026-07-05)

echo ""
echo "Fatto. Ctrl+Shift+R sul calendario."
