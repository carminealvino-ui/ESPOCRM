#!/usr/bin/env bash
# PR #99 — Provvigioni su imponibile IVA esclusa + totaleProvvigioni corretto.
# NON tocca metadata Quote (stato/finanziamento → PR #90).
#
# Ordine: deployare DOPO PR #90 (fix-contratto-quote-9999).
#
# Uso:
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-contratto-stato-provvigioni-9999/tools/deploy-provvigioni-imponibile-netto.sh?t=$(date +%s)" | bash
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="cursor/fix-contratto-stato-provvigioni-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"
TS="$(date +%s)"

cd "${CRM_ROOT}" || exit 1

echo "=== PR #99: provvigioni imponibile netto ==="
echo "=== CRM: ${CRM_ROOT} ==="

fetch() {
  local path="$1"
  mkdir -p "$(dirname "$path")"
  curl -fL --retry 8 --retry-all-errors --retry-delay 2 --retry-max-time 120 \
    "${BASE}/${path}?t=${TS}" -o "${path}"
  echo "OK ${path}"
}

FILES=(
  custom/Espo/Custom/Services/ProvvigioneManager.php
  custom/Espo/Custom/Services/QuotePricingCalculator.php
  custom/Espo/Custom/Hooks/Provvigione/AccrualAndAmount.php
  custom/Espo/Custom/Hooks/Quote/AfterSaveTotaleProvvigioni.php
  custom/Espo/Custom/Hooks/Quote/ProvvigioneConsolidata.php
  custom/Espo/Custom/Hooks/Quote/SetPresentedWhenNumeroContratto.php
  tools/migrate-ricalcola-provvigioni-contratti.php
)

for rel in "${FILES[@]}"; do
  fetch "${rel}"
done

rm -f custom/Espo/Custom/Hooks/Provvigione/BeforeSaveLegacy.php

php clear_cache.php
php rebuild.php

echo ""
echo "=== Ricalcolo provvigioni su tutti i contratti ==="
php tools/migrate-ricalcola-provvigioni-contratti.php

php clear_cache.php

echo ""
echo "=== Fatto PR #99 ==="
echo "Se GET /Provvigione dà 500 InvitoAFatturare, eseguire prima:"
echo "  curl -fsSL \"https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/provvigione-layout-pulizia-9999/tools/deploy-invitoa-fatturare-metadata.sh?t=\$(date +%s)\" | bash"
echo ""
echo "Per ricalcolare TUTTI i contratti:"
echo "  curl -fsSL \"https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}/tools/deploy-ricalcola-provvigioni-tutti.sh?t=\$(date +%s)\" | bash"
echo ""
echo "Atteso TSIGA: base 15% su €4909,09 → €736,36; plus 35% su €818,18 → €286,36"
echo "totaleProvvigioni = somma importo consolidato (non descrizione testo)"
echo ""
echo "Prossimo (PR #9 — doppio Crea prodotto):"
echo "  curl -fsSL \"https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-doppio-crea-prodotto-9999/tools/deploy-doppio-crea-prodotto.sh?t=\$(date +%s)\" | bash"
