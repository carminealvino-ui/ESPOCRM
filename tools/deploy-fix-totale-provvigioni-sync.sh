#!/usr/bin/env bash
# Fix Provvigioni Totali sul contratto = somma consolidati (non 15%+35% imponibile)
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-totale-provvigioni-sync-9999/tools/deploy-fix-totale-provvigioni-sync.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/fix-totale-provvigioni-sync-9999"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

FILES=(
  "custom/Espo/Custom/Hooks/Quote/BeforeSave.php"
  "custom/Espo/Custom/Hooks/Quote/NormalizeStati.php"
  "custom/Espo/Custom/Hooks/Quote/SyncProvvigioniStato.php"
  "custom/Espo/Custom/Hooks/Quote/BeforeSaveTotaleProvvigioni.php"
  "custom/Espo/Custom/Hooks/Quote/AfterSaveTotaleProvvigioni.php"
  "custom/Espo/Custom/Hooks/Provvigione/AfterSaveRefreshQuoteTotale.php"
  "custom/Espo/Custom/Services/ProvvigioneManager.php"
  "custom/Espo/Custom/Services/ProvvigioneStatusSync.php"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json"
  "custom/Espo/Custom/Resources/metadata/logicDefs/Quote.json"
  "custom/Espo/Custom/Resources/layouts/Quote/detail.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Quote.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Provvigione.json"
  "custom/Espo/Custom/Resources/metadata/logicDefs/Provvigione.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Provvigione.json"
  "custom/Espo/Custom/Resources/metadata/formula/Provvigione.json"
  "tools/backfill-totale-provvigioni.php"
  "tools/migrate-allineamento-stati-contratti-provvigioni.php"
)

echo "=== Deploy sync totaleProvvigioni da ${BRANCH} ==="
for rel in "${FILES[@]}"; do
  mkdir -p "${CRM_ROOT}/$(dirname "${rel}")"
  curl -fsSL "${BASE}/${rel}?t=$(date +%s)" -o "${CRM_ROOT}/${rel}"
  echo "OK ${rel}"
done

cd "${CRM_ROOT}"

if grep -q 'public function afterSave' custom/Espo/Custom/Hooks/Quote/BeforeSave.php 2>/dev/null; then
  echo "ERRORE: BeforeSave.php contiene ancora afterSave legacy — deploy incompleto"
  exit 1
fi

php clear_cache.php
php rebuild.php

echo ""
echo "=== Dry-run allineamento stati + mapping provvigioni ==="
php tools/migrate-allineamento-stati-contratti-provvigioni.php --dry-run || true

echo ""
echo "=== Allineamento stati + mapping provvigioni su tutti i contratti ==="
php tools/migrate-allineamento-stati-contratti-provvigioni.php

echo ""
echo "=== Dry-run backfill ==="
php tools/backfill-totale-provvigioni.php --dry-run || true

echo ""
echo "=== Backfill Quote.totaleProvvigioni (SQL diretto) ==="
php tools/backfill-totale-provvigioni.php

php clear_cache.php

if grep -q 'BeforeSaveTotaleProvvigioni' custom/Espo/Custom/Hooks/Quote/BeforeSaveTotaleProvvigioni.php \
  && grep -q 'SyncProvvigioniStato' custom/Espo/Custom/Hooks/Quote/SyncProvvigioniStato.php \
  && grep -q 'In pagamento' custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json \
  && grep -q 'updateQuoteTotaleProvvigioniInDatabase' custom/Espo/Custom/Services/ProvvigioneManager.php \
  && ! grep -q 'public function afterSave' custom/Espo/Custom/Hooks/Quote/BeforeSave.php; then
  echo ""
  echo "VERIFICA OK: enum allineati + mapping provvigioni + totale sempre conteggiato"
else
  echo ""
  echo "ATTENZIONE: verifica manuale file deployati"
fi

echo ""
echo "=== Fine. Ctrl+Shift+R nel browser ==="
echo "Atteso es. Contratto_00152: 654.54 + 95.46 = 750.00 (non 2181.82)"
