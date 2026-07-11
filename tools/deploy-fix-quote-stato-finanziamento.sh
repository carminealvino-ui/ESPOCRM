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
  custom/Espo/Custom/Hooks/Quote/ProvvigioneConsolidata.php
  custom/Espo/Custom/Hooks/Quote/SyncProvvigioniStato.php
  custom/Espo/Custom/Services/QuotePricingCalculator.php
  custom/Espo/Custom/Services/ProvvigioneStatusSync.php
  custom/Espo/Custom/Resources/metadata/logicDefs/Quote.json
  custom/Espo/Custom/Resources/metadata/formula/Quote.json
  custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json
  custom/Espo/Custom/Resources/metadata/entityDefs/Opportunity.json
  custom/Espo/Custom/Resources/i18n/it_IT/Quote.json
  custom/Espo/Custom/Resources/i18n/it_IT/Opportunity.json
  tools/diagnose-quote-save.php
)

for rel in "${FILES[@]}"; do
  fetch "${rel}"
done

php clear_cache.php
php rebuild.php
php clear_cache.php

echo ""
echo "=== Fatto ==="
echo "  - Ricalcolo prezzi solo se cambiano importi/articoli (non stato/finanziamento)"
echo "  - Errori hook loggati senza bloccare il salvataggio"
echo "  - statoFinanziamento Respinto/Annullato → provvigioni Inesigibile"
echo "  - Formula Quote semplificata (niente ricalcolo nome per CreateContratto)"
echo "  - optimisticConcurrencyControl disattivato su Quote"
echo "  - enum statoFinanziamento: valori legacy ripristinati (In valutazione, In attesa di OTP, ...)"
echo ""
echo "Se ancora errore, diagnostica da SSH:"
echo "  php tools/diagnose-quote-save.php 6a462adfd3eedc239 \"Approvato\""
