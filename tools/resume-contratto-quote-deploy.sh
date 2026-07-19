#!/usr/bin/env bash
# Riprende deploy interrotto (es. errore 429 GitHub). Scarica solo file mancanti + rebuild.
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-main}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"
FETCH_DELAY="${FETCH_DELAY:-0.5}"

cd "${CRM_ROOT}" || exit 1

echo "=== Ripresa deploy Contratto Quote ==="
echo "=== CRM root: ${CRM_ROOT} ==="
echo "=== Branch: ${BRANCH} ==="
echo ""
echo "Attendo 90s (cooldown rate limit GitHub)..."
sleep 90

fetch() {
  local rel="$1"
  local dir
  dir="$(dirname "${rel}")"
  mkdir -p "${dir}"

  local attempt=1
  local wait=8
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
    echo "RETRY ${rel} (HTTP ${http_code}, ${attempt}/${max_attempts}, ${wait}s)..."
    sleep "$wait"
    if [[ $wait -lt 32 ]]; then
      wait=$((wait * 2))
    fi
    attempt=$((attempt + 1))
  done

  echo "ERRORE ${rel}: impossibile scaricare"
  return 1
}

# File da bottomPanelsDetail in poi (dove si è fermato il deploy) + client + tools
REMAINING=(
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
  client/custom/src/views/provvigione/record/detail.js
  client/custom/src/views/provvigione/record/edit.js
)

echo ""
echo "=== Download file rimanenti ==="
for rel in "${REMAINING[@]}"; do
  fetch "${rel}"
done

echo ""
echo "=== Rebuild + cache ==="
php clear_cache.php
php rebuild.php
rm -rf data/cache/* 2>/dev/null || true
chmod -R u+rwX data/cache 2>/dev/null || true

echo ""
echo "=== Tools + verifica + backfill ==="
fetch tools/verify-contratto-quote-deploy.php
fetch tools/backfill-quote-provvigioni.php
fetch tools/backfill-quote-totale-provvigioni.php

php tools/verify-contratto-quote-deploy.php || exit 1
php tools/backfill-quote-provvigioni.php
php tools/backfill-quote-totale-provvigioni.php

php clear_cache.php
rm -rf data/cache/* 2>/dev/null || true

echo ""
php tools/verify-contratto-quote-deploy.php
echo ""
echo "Fatto. Ctrl+Shift+R nel browser."
