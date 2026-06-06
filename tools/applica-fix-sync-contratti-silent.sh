#!/usr/bin/env bash
# Fix sync prodotticontratti: SQL diretto (no relateById con silent).
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/productprice-dual-iva-listino-codice-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}" || exit 1

echo "=== Backup sync contratti ==="
TS="$(date +%Y%m%d-%H%M%S)"
BK="custom/backup-layouts/prodotti-contratti-${TS}"
mkdir -p "${BK}"
cp -a custom/Espo/Custom/Services/ProductContrattiSync.php "${BK}/" 2>/dev/null || true
cp -a custom/Espo/Custom/Hooks/Quote/SyncProductContratti.php "${BK}/" 2>/dev/null || true
echo "Backup: ${BK}/"

for f in \
  custom/Espo/Custom/Services/ProductContrattiSync.php \
  custom/Espo/Custom/Hooks/Quote/SyncProductContratti.php \
  tools/backfill-prodotti-contratti-link.php
do
  mkdir -p "$(dirname "${f}")"
  curl -fsSL "${BASE}/${f}?t=$(date +%s)" -o "${f}"
  echo "OK ${f}"
done

php command.php rebuild
php command.php clear-cache 2>/dev/null || true
rm -rf data/cache/* 2>/dev/null || true
chmod -R u+rwX data/cache 2>/dev/null || true

echo ""
echo "=== Backfill prodotticontratti (SQL diretto) ==="
php tools/backfill-prodotti-contratti-link.php

echo ""
echo "Fix applicato. Salva un contratto e apri Prodotto > Contratti (Ctrl+F5)."
echo "Rollback: cp ${BK}/ProductContrattiSync.php custom/Espo/Custom/Services/ && php command.php rebuild"
