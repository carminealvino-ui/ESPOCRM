#!/usr/bin/env bash
# Hotfix: ripristina view JS Quote mancanti (404 su dettaglio contratto).
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/fix-contratto-quote-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}" || exit 1

FILES=(
  client/custom/src/handlers/quote/crea-prodotto-articoli.js
  client/custom/src/handlers/quote/ricalcola-provvigioni.js
  client/custom/src/views/quote/record/detail.js
  client/custom/src/views/quote/record/item.js
  client/custom/src/views/quote/record/panels/items.js
  client/custom/src/views/quote/record/panels/finanziamento.js
  client/custom/src/views/quote/fields/item-list.js
  client/custom/src/views/provvigione/record/detail.js
  client/custom/src/views/provvigione/record/edit.js
)

fetch() {
  local rel="$1"
  mkdir -p "$(dirname "${rel}")"
  curl -fsSL "${BASE}/${rel}?t=$(date +%s)" -o "${rel}"
  echo "OK ${rel}"
}

echo "=== Ripristino client Quote/Provvigione ==="
for rel in "${FILES[@]}"; do
  fetch "${rel}"
done

php clear_cache.php
rm -rf data/cache/* 2>/dev/null || true

echo ""
echo "Fatto. Ctrl+Shift+R e riapri il contratto."
