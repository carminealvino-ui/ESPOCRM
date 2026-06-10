#!/usr/bin/env bash
# Deploy: Prezzo Codice editabile sugli articoli contratto (Quote itemList).
# Uso: bash tools/deploy-quote-prezzo-codice-editabile.sh ~/public_html/crm/mec-group

set -euo pipefail

CRM_ROOT="${1:-$(pwd)}"
BRANCH="${2:-cursor/quote-prezzo-codice-editabile-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

FILES=(
  "custom/Espo/Custom/Resources/metadata/entityDefs/QuoteItem.json"
  "custom/Espo/Custom/Services/QuotePricingCalculator.php"
)

for rel in "${FILES[@]}"; do
  target="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${target}")"
  curl -fsSL -o "${target}" "${BASE}/${rel}?t=$(date +%s)"
  echo "OK ${target}"
done

echo ""
echo "Verifica itemNotReadOnly su QuoteItem:"
grep -A3 '"prezzoCodice"' "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/entityDefs/QuoteItem.json" | head -8

if [[ -f "${CRM_ROOT}/clear_cache.php" ]]; then
  (cd "${CRM_ROOT}" && php clear_cache.php && php rebuild.php)
fi

echo ""
echo "Fatto. Ctrl+F5 nel browser."
echo "In compilazione contratto la colonna Prezzo Codice è editabile; il valore manuale non viene sovrascritto al salvataggio."
