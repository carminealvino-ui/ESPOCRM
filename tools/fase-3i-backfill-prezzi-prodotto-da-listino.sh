#!/usr/bin/env bash
# Ricalcola su Product: listPrice (netto), prezzoListinoIvaInclusa, prezzoCodice da CSV listino.
# Utile dopo aggiornamenti manuali in tab Prezzi (ProductPrice).
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
CSV="${CSV:-database/data/listino-ariel-prodotti-07052026.csv}"
PRICE_BOOK_ID="${PRICE_BOOK_ID:-07ce1b326cd314ca2}"

cd "${CRM_ROOT}" || exit 1

php tools/sync-listino-prodotti.php \
  --csv="${CSV}" \
  --price-book-id="${PRICE_BOOK_ID}" \
  --converti-iva-esclusa \
  --aliquota-iva=10

echo "Fatto. Eseguire: php command.php rebuild && rm -rf data/cache/*"
