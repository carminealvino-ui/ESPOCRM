#!/usr/bin/env bash
# Contratto Articoli: popola Prezzo di Listino e Prezzo Codice alla selezione prodotto.
# Autocontenuto: crea cartelle, scarica file con messaggi di errore chiari.
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/productprice-dual-iva-listino-codice-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}" || {
  echo "ERRORE: cartella CRM non trovata: ${CRM_ROOT}" >&2
  exit 1
}

TS="$(date +%Y%m%d-%H%M%S)"
BK="custom/backup-layouts/quote-item-catalog-prices-${TS}"
mkdir -p "${BK}"

FILES=(
  custom/Espo/Custom/Services/QuotePricingCalculator.php
  custom/Espo/Custom/Tools/Quote/Api/PostGetItemCatalogPrices.php
  custom/Espo/Custom/Resources/routes.json
  custom/Espo/Custom/Resources/client/custom/src/views/quote/fields/item-list.js
  custom/Espo/Custom/Resources/client/custom/src/views/quote/record/item.js
  client/custom/src/views/quote/fields/item-list.js
  client/custom/src/views/quote/record/item.js
)

echo "=== Backup file esistenti ==="
for f in "${FILES[@]}"; do
  cp -a "${f}" "${BK}/" 2>/dev/null || true
done
echo "Backup: ${BK}/"

download_file() {
  local rel="$1"
  local dest="${CRM_ROOT}/${rel}"
  local url="${BASE}/${rel}?t=$(date +%s)"
  local tmp

  mkdir -p "$(dirname "${dest}")"
  tmp="$(mktemp "${TMPDIR:-/tmp}/espo-deploy.XXXXXX")"

  if ! curl -fsSL "${url}" -o "${tmp}"; then
    rm -f "${tmp}"
    echo "ERRORE download: ${url}" >&2
    exit 1
  fi

  mv "${tmp}" "${dest}"
  echo "OK ${rel}"
}

echo ""
echo "=== Download file da GitHub (${BRANCH}) ==="
for f in "${FILES[@]}"; do
  download_file "${f}"
done

echo ""
echo "=== Verifica PHP ==="
php -l custom/Espo/Custom/Services/QuotePricingCalculator.php
php -l custom/Espo/Custom/Tools/Quote/Api/PostGetItemCatalogPrices.php

echo ""
echo "=== Rebuild EspoCRM ==="
php command.php rebuild
php command.php clear-cache 2>/dev/null || true
rm -rf data/cache/* 2>/dev/null || true
chmod -R u+rwX data/cache 2>/dev/null || true

echo ""
echo "Deploy completato."
echo "Test: apri un contratto, seleziona listino + prodotto → Prezzo di Listino e Prezzo Codice compilati."
echo ""
echo "Se curl | bash fallisce, usa:"
echo "  curl -fsSL \"${BASE}/tools/applica-quote-item-catalog-prices.sh?t=\$(date +%s)\" -o /tmp/applica-quote-item-catalog-prices.sh && bash /tmp/applica-quote-item-catalog-prices.sh"
echo ""
echo "Rollback: ripristina da ${BK}/ && php command.php rebuild"
