#!/usr/bin/env bash
# PR #99 — Ricalcola provvigioni su TUTTI i contratti in produzione.
# Richiede codice provvigioni già deployato (deploy-provvigioni-imponibile-netto.sh).
#
# Uso:
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-contratto-stato-provvigioni-9999/tools/deploy-ricalcola-provvigioni-tutti.sh?t=$(date +%s)" | bash
#
# Solo anteprima:
#   curl -fsSL ".../deploy-ricalcola-provvigioni-tutti.sh" | bash -s -- --dry-run
#
# Un contratto:
#   curl -fsSL ".../deploy-ricalcola-provvigioni-tutti.sh" | bash -s -- --codice=Contratto_00144
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="cursor/fix-contratto-stato-provvigioni-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"
TS="$(date +%s)"
EXTRA_ARGS=("$@")

cd "${CRM_ROOT}" || exit 1

echo "=== Ricalcolo provvigioni su tutti i contratti ==="
echo "=== CRM: ${CRM_ROOT} ==="

fetch() {
  local path="$1"
  mkdir -p "$(dirname "$path")"
  curl -fL --retry 8 --retry-all-errors --retry-delay 2 --retry-max-time 120 \
    "${BASE}/${path}?t=${TS}" -o "${path}"
  echo "OK ${path}"
}

# Aggiorna script + servizi provvigioni (idempotente)
for rel in \
  custom/Espo/Custom/Services/ProvvigioneManager.php \
  custom/Espo/Custom/Services/QuotePricingCalculator.php \
  custom/Espo/Custom/Hooks/Provvigione/AccrualAndAmount.php \
  custom/Espo/Custom/Hooks/Quote/ProvvigioneConsolidata.php \
  tools/migrate-ricalcola-provvigioni-contratti.php
do
  fetch "${rel}"
done

php clear_cache.php

echo ""
if [[ " ${EXTRA_ARGS[*]} " == *" --dry-run "* ]]; then
  echo "=== Anteprima (dry-run) ==="
else
  echo "=== Esecuzione ricalcolo ==="
fi

php tools/migrate-ricalcola-provvigioni-contratti.php "${EXTRA_ARGS[@]}"

php clear_cache.php

echo ""
echo "=== Fatto. Ctrl+Shift+R sulla lista Contratti. ==="
echo "Esempio TSIGA Contratto_00144: totale atteso ~€1022,72 (€736,36 base + €286,36 plus)"
