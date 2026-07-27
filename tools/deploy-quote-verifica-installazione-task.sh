#!/usr/bin/env bash
# Deploy: To-Do + promemoria verifica installazione su Contratto.
set -euo pipefail
ROOT="${1:-$HOME/public_html/crm/mec-group}"
BRANCH="cursor/quote-verifica-installazione-task-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "$ROOT"

files=(
  "custom/Espo/Custom/Services/QuoteInstallazioneVerificaTaskSync.php"
  "custom/Espo/Custom/Hooks/Quote/SyncVerificaInstallazioneTask.php"
  "custom/Espo/Custom/Hooks/Task/ApplyVerificaInstallazioneEsito.php"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Task.json"
  "custom/Espo/Custom/Resources/metadata/logicDefs/Task.json"
  "custom/Espo/Custom/Resources/layouts/Quote/detail.json"
  "custom/Espo/Custom/Resources/layouts/Task/detail.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Quote.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Task.json"
  "tools/backfill-verifica-installazione-task.php"
)

for rel in "${files[@]}"; do
  mkdir -p "$(dirname "$rel")"
  curl -fsSL "${BASE}/${rel}" -o "${rel}"
  echo "OK ${rel}"
done

php clear_cache.php
rm -rf data/cache/*
php rebuild.php

echo
echo "Deploy OK."
echo "Dry-run backfill contratti esistenti:"
echo "  php tools/backfill-verifica-installazione-task.php --dry-run --limit=20"
echo "Apply:"
echo "  php tools/backfill-verifica-installazione-task.php --apply"
