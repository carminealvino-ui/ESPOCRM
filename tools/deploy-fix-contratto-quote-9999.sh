#!/usr/bin/env bash
# Deploy fix contratto (Quote): ordinamento, finanziamento sotto articoli, provvigioni corrette.
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/fix-contratto-quote-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"
SKIP_BACKUP="${SKIP_BACKUP:-0}"
FETCH_DELAY="${FETCH_DELAY:-0.35}"

cd "${CRM_ROOT}" || {
  echo "ERRORE: directory CRM non trovata: ${CRM_ROOT}"
  exit 1
}

echo "=== CRM root: ${CRM_ROOT} ==="
echo "=== Branch: ${BRANCH} ==="

FILES=(
  custom/Espo/Custom/Hooks/Quote/BeforeSave.php
  custom/Espo/Custom/Hooks/Quote/SyncContractPricing.php
  custom/Espo/Custom/Hooks/Quote/SyncFinanziamentoFromOpportunity.php
  custom/Espo/Custom/Hooks/Quote/AfterSaveTotaleProvvigioni.php
  custom/Espo/Custom/Hooks/Quote/ProvvigioneConsolidata.php
  custom/Espo/Custom/Hooks/Quote/SetPresentedWhenNumeroContratto.php
  custom/Espo/Custom/Hooks/Quote/NormalizeDefaults.php
  custom/Espo/Custom/Hooks/Quote/ComputeImportoSaldo.php
  custom/Espo/Custom/Hooks/Quote/AssignNumberACodiceContratto.php
  custom/Espo/Custom/Hooks/Provvigione/AfterSaveContratto.php
  custom/Espo/Custom/Hooks/Provvigione/AccrualAndAmount.php
  custom/Espo/Custom/Services/ProvvigioneManager.php
  custom/Espo/Custom/Services/QuotePricingCalculator.php
  custom/Espo/Custom/Services/QuoteProvvigioniSync.php
  custom/Espo/Custom/Services/RegolaProvvigionaleCalculator.php
  custom/Espo/Custom/Tools/Quote/Api/PostGetItemCatalogPrices.php
  custom/Espo/Custom/Resources/metadata/entityDefs/QuoteItem.json
  custom/Espo/Custom/Resources/metadata/clientDefs/QuoteItem.json
  custom/Espo/Custom/Resources/layouts/QuoteItem/listItem.json
  custom/Espo/Custom/Resources/i18n/it_IT/QuoteItem.json
  custom/Espo/Custom/Entities/RegolaProvvigionale.php
  custom/Espo/Custom/Repositories/RegolaProvvigionale.php
  custom/Espo/Custom/Resources/metadata/scopes/RegolaProvvigionale.json
  custom/Espo/Custom/Resources/metadata/entityDefs/RegolaProvvigionale.json
  custom/Espo/Custom/Resources/metadata/clientDefs/RegolaProvvigionale.json
  custom/Espo/Custom/Resources/layouts/RegolaProvvigionale/detail.json
  custom/Espo/Custom/Resources/layouts/RegolaProvvigionale/list.json
  custom/Espo/Custom/Resources/i18n/it_IT/RegolaProvvigionale.json
  custom/Espo/Custom/Actions/Quote/RicalcolaProvvigioni.php
  custom/Espo/Custom/Controllers/Quote.php
  custom/Espo/Custom/Resources/layouts/Quote/detail.json
  custom/Espo/Custom/Resources/layouts/Quote/bottomPanelsDetail.json
  custom/Espo/Custom/Resources/layouts/Quote/finanziamento.json
  custom/Espo/Custom/Resources/layouts/Quote/list.json
  custom/Espo/Custom/Resources/layouts/Quote/relationships/provvigioni.json
  custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json
  custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json
  custom/Espo/Custom/Resources/metadata/logicDefs/Quote.json
  custom/Espo/Custom/Resources/metadata/formula/Quote.json
  custom/Espo/Custom/Resources/metadata/app/actions.json
  custom/Espo/Custom/Resources/i18n/it_IT/Quote.json
  client/custom/src/handlers/quote/ricalcola-provvigioni.js
  client/custom/src/handlers/quote/catalog-prices.js
  client/custom/src/handlers/quote/refresh-provvigioni-on-save.js
  client/custom/src/handlers/quote/crea-prodotto-articoli.js
  client/custom/src/views/quote/record/detail.js
  client/custom/src/views/quote/record/edit.js
  client/custom/src/views/quote/record/item.js
  client/custom/src/views/quote/fields/item-list.js
  client/custom/src/views/quote/record/panels/items.js
  client/custom/src/views/quote/record/panels/finanziamento.js
  client/custom/css/custom-ui.css
  client/custom/src/views/provvigione/record/detail.js
  client/custom/src/views/provvigione/record/edit.js
)

fetch() {
  local rel="$1"
  local dir
  dir="$(dirname "${rel}")"
  mkdir -p "${dir}"

  local attempt=1
  local wait=4
  local max_attempts=6
  local http_code

  while [[ $attempt -le $max_attempts ]]; do
    http_code=$(curl -sS -L -w "%{http_code}" -o "${rel}.tmp" \
      "${BASE}/${rel}?t=$(date +%s)-${RANDOM}" 2>/dev/null || echo "000")

    if [[ "$http_code" == "200" ]] && [[ -s "${rel}.tmp" ]]; then
      mv "${rel}.tmp" "${rel}"
      echo "OK ${rel}"
      sleep "${FETCH_DELAY}"
      return 0
    fi

    rm -f "${rel}.tmp" 2>/dev/null || true

    if [[ "$http_code" == "429" || "$http_code" == "503" || "$http_code" == "000" ]]; then
      echo "RETRY ${rel} (HTTP ${http_code}, tentativo ${attempt}/${max_attempts}, attendo ${wait}s)..."
      sleep "$wait"
      if [[ $wait -lt 32 ]]; then
        wait=$((wait * 2))
      fi
      attempt=$((attempt + 1))
      continue
    fi

    echo "ERRORE ${rel}: HTTP ${http_code}"
    return 1
  done

  echo "ERRORE ${rel}: rate limit GitHub dopo ${max_attempts} tentativi"
  echo "Attendi 2-3 minuti e rilancia: SKIP_BACKUP=1 bash tools/deploy-fix-contratto-quote-9999.sh"
  return 1
}

if [[ "${SKIP_BACKUP}" != "1" ]]; then
  echo ""
  echo "=== Backup ==="
  TS="$(date +%Y%m%d-%H%M%S)"
  BK="custom/backup-layouts/contratto-quote-fix-${TS}"
  mkdir -p "${BK}"

  for f in "${FILES[@]}"; do
    cp -a "${f}" "${BK}/" 2>/dev/null || true
  done
  echo "Backup: ${BK}/"

  for legacy in \
    custom/Espo/Custom/Hooks/Quote/SyncTotaleProvvigioni.php \
    custom/Espo/Custom/Hooks/Provvigione/UpdateQuoteTotaleProvvigioni.php \
    custom/Espo/Custom/Services/QuoteTotaleProvvigioniService.php \
    custom/Espo/Custom/Resources/layouts/Quote/detailBottom.json
  do
    if [[ -f "${legacy}" ]]; then
      cp -a "${legacy}" "${BK}/"
      rm -f "${legacy}"
      echo "RIMOSSO ${legacy}"
    fi
  done
else
  echo ""
  echo "=== Backup saltato (SKIP_BACKUP=1) ==="
fi

echo ""
echo "=== Download da ${BRANCH} ==="
for rel in "${FILES[@]}"; do
  fetch "${rel}"
done

echo ""
echo "=== Rebuild (registra entity RegolaProvvigionale) ==="
php clear_cache.php
php rebuild.php
rm -rf data/cache/* 2>/dev/null || true
chmod -R u+rwX data/cache 2>/dev/null || true

echo ""
echo "=== Schema provvigione + seed regole ==="
fetch tools/run-provvigione-schema-patch.php
fetch tools/run-regola-provvigionale-seed.php
fetch database/2026-05-26-gdl-ariel-2026-regole-provvigioni-seed.sql
fetch database/2026-05-26-arquati-pnc-regole-provvigioni-seed.sql
fetch database/2026-07-06-bonus-weekend-regola-provvigioni-seed.sql
fetch database/2026-07-06-ariel-minus-35-regola-provvigioni-seed.sql
php tools/run-provvigione-schema-patch.php
php tools/run-regola-provvigionale-seed.php

echo ""
echo "=== Verifica file scaricati ==="
fetch tools/verify-contratto-quote-deploy.php
php tools/verify-contratto-quote-deploy.php || {
  echo "ERRORE: verifica deploy fallita — controllare i file sopra"
  exit 1
}

echo ""
echo "=== Backfill provvigioni (ricalcolo completo) ==="
fetch tools/backfill-quote-provvigioni.php
php tools/backfill-quote-provvigioni.php

echo ""
echo "=== Backfill totaleProvvigioni (importoConsolidato) ==="
fetch tools/backfill-quote-totale-provvigioni.php
php tools/backfill-quote-totale-provvigioni.php

echo ""
echo "=== Cache finale ==="
php clear_cache.php
rm -rf data/cache/* 2>/dev/null || true
chmod -R u+rwX data/cache 2>/dev/null || true

echo ""
echo "=== Verifica finale ==="
php tools/verify-contratto-quote-deploy.php

echo ""
echo "Fatto. Ctrl+Shift+R nel browser."
echo "Atteso: Articoli → Finanziamento → Provvigioni; totale = somma importo consolidato."
