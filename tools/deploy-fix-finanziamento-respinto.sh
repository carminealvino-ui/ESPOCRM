#!/usr/bin/env bash
# Deploy fix: Stato Finanziamento Respinto non più forzato ad Annullato.
set -euo pipefail
ROOT="${1:-$HOME/public_html/crm/mec-group}"
BRANCH="cursor/fix-finanziamento-respinto-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "$ROOT"
curl -fsSL "${BASE}/custom/Espo/Custom/Services/ContrattoStatiRules.php" \
  -o custom/Espo/Custom/Services/ContrattoStatiRules.php
curl -fsSL "${BASE}/custom/Espo/Custom/Hooks/Quote/SyncStatiContrattoFinanziamento.php" \
  -o custom/Espo/Custom/Hooks/Quote/SyncStatiContrattoFinanziamento.php
curl -fsSL "${BASE}/custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json" \
  -o custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json
curl -fsSL "${BASE}/custom/Espo/Custom/Resources/i18n/it_IT/Quote.json" \
  -o custom/Espo/Custom/Resources/i18n/it_IT/Quote.json
curl -fsSL "${BASE}/tools/test-contratto-stati-respinto.php" \
  -o tools/test-contratto-stati-respinto.php
curl -fsSL "${BASE}/tools/verify-quote-stati-deploy.php" \
  -o tools/verify-quote-stati-deploy.php

php clear_cache.php
rm -rf data/cache/*
php rebuild.php
php tools/verify-quote-stati-deploy.php
php tools/test-contratto-stati-respinto.php

echo
echo "Deploy OK. Riapri il contratto Tudose Maria, imposta Stato Finanziamento=Respinto e salva."
echo "Poi ricarica i KPI: Finanziamenti KO=1, Netti=4."
