#!/usr/bin/env bash
# Fix errore 500 salvataggio statoFinanziamento su Contratto (Quote)
#
# Uso:
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-quote-stato-finanziamento-500-9999/tools/deploy-fix-quote-stato-finanziamento.sh?t=$(date +%s)" | bash
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="cursor/fix-quote-stato-finanziamento-500-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"
TS="$(date +%s)"

cd "${CRM_ROOT}" || exit 1

echo "=== Fix salvataggio statoFinanziamento Contratto ==="

fetch() {
  local path="$1"
  mkdir -p "$(dirname "$path")"
  curl -fL --retry 8 --retry-all-errors --retry-delay 2 --retry-max-time 120 \
    "${BASE}/${path}?t=${TS}" -o "${path}"
  echo "OK ${path}"
}

FILES=(
  custom/Espo/Custom/Hooks/Quote/SyncContractPricing.php
  custom/Espo/Custom/Hooks/Quote/AfterSaveTotaleProvvigioni.php
  custom/Espo/Custom/Hooks/Quote/SyncProvvigioniStato.php
  custom/Espo/Custom/Services/QuotePricingCalculator.php
  custom/Espo/Custom/Services/ProvvigioneStatusSync.php
  custom/Espo/Custom/Resources/metadata/logicDefs/Quote.json
)

for rel in "${FILES[@]}"; do
  fetch "${rel}"
done

php clear_cache.php
php rebuild.php
php clear_cache.php

echo ""
echo "=== Fatto ==="
echo "  - Salvataggio solo campi Finanziamento: niente ricalcolo articoli/prezzi"
echo "  - statoFinanziamento Respinto/Annullato → provvigioni Inesigibile"
echo "  - Ctrl+Shift+R e riprova cambio Stato Finanziamento"
