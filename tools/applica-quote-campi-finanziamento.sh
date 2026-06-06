#!/usr/bin/env bash
# Contratto (Quote): campi finanziamento da Opportunità + migrazione dati.
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/productprice-dual-iva-listino-codice-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}" || exit 1

echo "=== Backup Quote finanziamento ==="
TS="$(date +%Y%m%d-%H%M%S)"
BK="custom/backup-layouts/quote-finanziamento-${TS}"
mkdir -p "${BK}"
cp -a custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json "${BK}/" 2>/dev/null || true
cp -a custom/Espo/Custom/Resources/layouts/Quote/detail.json "${BK}/" 2>/dev/null || true
cp -a custom/Espo/Custom/Resources/metadata/logicDefs/Quote.json "${BK}/" 2>/dev/null || true
cp -a custom/Espo/Custom/Resources/i18n/it_IT/Quote.json "${BK}/" 2>/dev/null || true
echo "Backup: ${BK}/"

for f in \
  custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json \
  custom/Espo/Custom/Resources/metadata/logicDefs/Quote.json \
  custom/Espo/Custom/Resources/layouts/Quote/detail.json \
  custom/Espo/Custom/Resources/i18n/it_IT/Quote.json \
  custom/Espo/Custom/Hooks/Quote/SyncFinanziamentoFromOpportunity.php \
  custom/Espo/Custom/Actions/Opportunity/CreateContratto.php \
  tools/migra-quote-finanziamento-da-opportunita.php
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
echo "=== Migrazione dati Opportunità → Contratto ==="
php tools/migra-quote-finanziamento-da-opportunita.php

echo ""
echo "Deploy completato. Apri un Contratto collegato a un'Opportunità e Ctrl+F5."
echo "Rollback: ripristina file da ${BK}/ e php command.php rebuild"
