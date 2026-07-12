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
  custom/Espo/Custom/Hooks/InvitoAFatturare/BeforeSave.php
  custom/Espo/Custom/Hooks/Quote/SanitizeOrphanQuoteItemProducts.php
  custom/Espo/Custom/Hooks/Quote/SyncQuoteItemPrezzoCodice.php
  custom/Espo/Custom/Hooks/Quote/EnsureQuoteItemListNames.php
  custom/Espo/Custom/Hooks/Quote/SyncContractPricing.php
  custom/Espo/Custom/Hooks/Quote/AfterSaveTotaleProvvigioni.php
  custom/Espo/Custom/Hooks/Quote/ProvvigioneConsolidata.php
  custom/Espo/Custom/Hooks/Quote/SyncProvvigioniStato.php
  custom/Espo/Custom/Services/QuotePricingCalculator.php
  custom/Espo/Custom/Services/ProvvigioneStatusSync.php
  custom/Espo/Custom/Services/ProvvigioneManager.php
  custom/Espo/Custom/Resources/metadata/logicDefs/Quote.json
  custom/Espo/Custom/Resources/metadata/formula/Quote.json
  custom/Espo/Custom/Resources/metadata/formula/QuoteItem.json
  custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json
  custom/Espo/Custom/Resources/metadata/entityDefs/QuoteItem.json
  custom/Espo/Custom/Resources/metadata/entityDefs/Opportunity.json
  custom/Espo/Custom/Resources/i18n/it_IT/Quote.json
  custom/Espo/Custom/Resources/i18n/it_IT/Opportunity.json
  custom/Espo/Custom/Resources/client/custom/src/views/quote/record/item.js
  custom/Espo/Custom/Resources/client/custom/src/views/quote/record/detail.js
  custom/Espo/Custom/Resources/client/custom/src/views/quote/fields/item-list.js
  custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json
  custom/Espo/Custom/Resources/client/custom/src/handlers/quote/sanitize-articoli-before-save.js
  custom/Espo/Custom/Resources/client/custom/src/handlers/quote/catalog-prices.js
  tools/bonifica-quote-item-product-orphan.php
  tools/diagnose-quote-save.php
  tools/bonifica-stato-finanziamento-legacy.php
  tools/bonifica-quote-status-legacy.php
)

for rel in "${FILES[@]}"; do
  fetch "${rel}"
done

ORPHAN_HOOKS=(
  custom/Espo/Custom/Hooks/Quote/SyncProvvigioniStatoFromContratto.php
)

for rel in "${ORPHAN_HOOKS[@]}"; do
  if [[ -f "${rel}" ]]; then
    rm -f "${rel}"
    echo "RIMOSSO hook obsoleto ${rel}"
  fi
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
echo "  - rimosso hook obsoleto SyncProvvigioniStatoFromContratto (causa 500)"
echo "  - enum status: valori legacy ripristinati (Invalido, Bozza, Appuntamento fissato)"
echo "  - Voce articoli: commento sotto prodotto opzionale (QuoteItem.name non required)"
echo "  - Salvataggio: se commento vuoto, name compilato server-side da prodotto"
echo "  - Riga articolo con prodotto cancellato: link rimosso (niente Bad request)"
echo "  - Prezzo Codice su ogni riga: modificabile, totali dalla somma righe (non catalogo)"
echo ""
echo "=== Prossimi passi (copia tutto il blocco) ==="
echo "cd \"${CRM_ROOT}\""
echo "php tools/bonifica-quote-item-product-orphan.php --dry-run --quote-id=6a36664f86ef51ce7"
echo "php tools/bonifica-quote-item-product-orphan.php --quote-id=6a36664f86ef51ce7"
echo "php tools/bonifica-quote-status-legacy.php --dry-run --quote-id=6a36664f86ef51ce7"
echo "php clear_cache.php"
echo ""
echo "Poi ricarica la pagina con Ctrl+F5 (svuota cache browser)."
echo "Il campo Stato non deve più apparire rosso 'non valido'."
