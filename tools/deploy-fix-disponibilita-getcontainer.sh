#!/usr/bin/env bash
# Fix Disponibilità Ricorrenti + visualizzazione in calendario (Espo 10).
#
# Uso:
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-disponibilita-getcontainer-9999/tools/deploy-fix-disponibilita-getcontainer.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/fix-disponibilita-getcontainer-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"
TS="$(date +%s)"

if [[ ! -d "${CRM_ROOT}" ]]; then
  echo "ERRORE: cartella CRM non trovata: ${CRM_ROOT}" >&2
  exit 1
fi

cd "${CRM_ROOT}"

PHP_BIN="${PHP_BIN:-php}"
if ! command -v "${PHP_BIN}" >/dev/null 2>&1; then
  echo "ERRORE: php non trovato. Imposta PHP_BIN=/percorso/php" >&2
  exit 1
fi

echo "=== Fix Disponibilita (genera + calendario) ==="
echo "CRM_ROOT=${CRM_ROOT}"

fetch() {
  local path="$1"
  mkdir -p "$(dirname "${path}")"
  if ! curl -fL --retry 8 --retry-all-errors --retry-delay 2 --retry-max-time 120 \
    "${BASE}/${path}?t=${TS}" -o "${path}"; then
    echo "ERRORE fetch: ${BASE}/${path}" >&2
    exit 1
  fi
  echo "OK ${path}"
}

FILES=(
  custom/Espo/Custom/Controllers/Disponibilita.php
  custom/Espo/Custom/Controllers/WorkingTimeCalendar.php
  custom/Espo/Custom/Actions/Disponibilita/GeneraDisponibilitaRicorrenti.php
  custom/Espo/Custom/Actions/WorkingTimeCalendar/GeneraDisponibilita.php
  custom/Espo/Custom/Hooks/Disponibilita/SetName.php
  custom/Espo/Custom/Hooks/WorkingTimeCalendar/AutoGeneraDisponibilita.php
  custom/Espo/Custom/Services/WorkingTimeCalendarDisponibilitaGenerator.php
  custom/Espo/Custom/Resources/metadata/clientDefs/Calendar.json
  custom/Espo/Custom/Resources/metadata/app/calendar.json
  custom/Espo/Custom/Resources/metadata/scopes/Disponibilita.json
  custom/Espo/Custom/Resources/metadata/app/actions.json
  custom/Espo/Custom/Resources/metadata/formula/Disponibilita.json
  custom/Espo/Custom/Resources/metadata/hooks/Disponibilita.json
  client/custom/src/views/working-time-calendar/record/detail.js
  client/custom/src/views/modals/disponibilita-ricorrenti.js
  tools/fix-disponibilita-calendario-display.php
  tools/diagnose-disponibilita-range.php
  tools/diagnose-calendario-generazione.php
)

for rel in "${FILES[@]}"; do
  fetch "${rel}"
done

"${PHP_BIN}" clear_cache.php
"${PHP_BIN}" rebuild.php
"${PHP_BIN}" clear_cache.php

echo ""
echo "=== Diagnostica (opzionale) ==="
if [[ -f tools/diagnose-disponibilita-range.php ]]; then
  echo "  php tools/diagnose-disponibilita-range.php --from=2026-07-20 --to=2026-08-02"
fi
if [[ -f tools/diagnose-calendario-generazione.php ]]; then
  echo "  php tools/diagnose-calendario-generazione.php --calendar=CALENDAR_ID --from=2026-07-20 --to=2026-08-02"
fi

echo ""
echo "=== Ripara record già generati (opzionale) ==="
if [[ -f tools/fix-disponibilita-calendario-display.php ]]; then
  "${PHP_BIN}" tools/fix-disponibilita-calendario-display.php || true
  "${PHP_BIN}" clear_cache.php
else
  echo "Script fix non presente: rigenera le disponibilità o copia tools/fix-disponibilita-calendario-display.php"
fi

echo ""
echo "=== Fatto ==="
echo "  - Disponibilita aggiunta al calendario"
echo "  - Orari fascia (orarioInizio/Fine) usati in agenda"
echo "  - assignedUserId sincronizzato per filtro utente"
echo ""
echo "Ricarica calendario con Ctrl+F5."
