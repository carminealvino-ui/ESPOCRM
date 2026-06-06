#!/usr/bin/env bash
# Sync prodotticontratti: hook Quote + backfill contratti esistenti.
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/productprice-dual-iva-listino-codice-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}" || exit 1

echo "=== Backup hook/sync contratti ==="
TS="$(date +%Y%m%d-%H%M%S)"
BK="custom/backup-layouts/prodotti-contratti-${TS}"
mkdir -p "${BK}"
cp -a custom/Espo/Custom/Services/ProductContrattiSync.php "${BK}/" 2>/dev/null || true
cp -a custom/Espo/Custom/Hooks/Quote/SyncProductContratti.php "${BK}/" 2>/dev/null || true
cp -a custom/Espo/Custom/Resources/metadata/clientDefs/Product.json "${BK}/" 2>/dev/null || true
echo "Backup: ${BK}/"

for f in \
  custom/Espo/Custom/Services/ProductContrattiSync.php \
  custom/Espo/Custom/Hooks/Quote/SyncProductContratti.php \
  custom/Espo/Custom/Resources/metadata/clientDefs/Product.json \
  tools/backfill-prodotti-contratti-link.php
do
  mkdir -p "$(dirname "${f}")"
  curl -fsSL "${BASE}/${f}?t=$(date +%s)" -o "${f}"
  echo "OK ${f}"
done

php command.php rebuild
php command.php clear-cache 2>/dev/null || true
rm -rf data/cache/*

echo ""
echo "=== Backfill prodotticontratti da articoli contratto ==="
php tools/backfill-prodotti-contratti-link.php

echo ""
echo "Deploy completato. Apri Prodotto > tab Contratti e Ctrl+F5."
echo "Rollback: ripristina file da ${BK}/ e php command.php rebuild"
