#!/usr/bin/env bash
# Deploy: To-Do + promemoria verifica installazione su Contratto.
# Evita curl -o diretto su path CRM (errore 23 su cPanel): scarica in /tmp poi cp.
set -euo pipefail
ROOT="${1:-$HOME/public_html/crm/mec-group}"
BRANCH="cursor/quote-verifica-installazione-task-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"
TMPDIR="${TMPDIR:-/tmp}/espo-deploy-verifica-inst-$$"

cd "$ROOT"
mkdir -p "$TMPDIR"
trap 'rm -rf "$TMPDIR"' EXIT

download_one() {
  local rel="$1"
  local tmp="$TMPDIR/$(basename "$rel")"
  local dest="$ROOT/$rel"

  echo ">> $rel"
  mkdir -p "$(dirname "$dest")"
  curl -fsSL "${BASE}/${rel}" -o "$tmp"
  cp -f "$tmp" "$dest"
  echo "   OK"
}

download_one "custom/Espo/Custom/Services/QuoteInstallazioneVerificaTaskSync.php"
download_one "custom/Espo/Custom/Hooks/Quote/SyncVerificaInstallazioneTask.php"
download_one "custom/Espo/Custom/Hooks/Task/ApplyVerificaInstallazioneEsito.php"
download_one "custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json"
download_one "custom/Espo/Custom/Resources/metadata/entityDefs/Task.json"
download_one "custom/Espo/Custom/Resources/metadata/logicDefs/Task.json"
download_one "custom/Espo/Custom/Resources/layouts/Quote/detail.json"
download_one "custom/Espo/Custom/Resources/layouts/Task/detail.json"
download_one "custom/Espo/Custom/Resources/i18n/it_IT/Quote.json"
download_one "custom/Espo/Custom/Resources/i18n/it_IT/Task.json"
download_one "tools/backfill-verifica-installazione-task.php"
download_one "tools/bonifica-invalido-data-installazione.php"
download_one "custom/Espo/Custom/Services/ContrattoStatiRules.php"

php clear_cache.php
rm -rf data/cache/*
php rebuild.php

echo
echo "Deploy OK."
echo "1) Bonifica Invalidi (dataInstallazione=null):"
echo "   php tools/bonifica-invalido-data-installazione.php --dry-run --limit=20"
echo "2) Backfill solo in scadenza (oggi→+60gg):"
echo "   php tools/backfill-verifica-installazione-task.php --dry-run --limit=20"
