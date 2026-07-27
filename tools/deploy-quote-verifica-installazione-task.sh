#!/usr/bin/env bash
# Deploy verifica installazione SENZA sovrascrivere Quote.json intero.
# Enum: solo patch in-place. Metadata feature: solo merge campi nuovi.
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

# PHP/hooks/Task metadata/layout — MAI Quote.json / Quote i18n interi
download_one "custom/Espo/Custom/Services/QuoteInstallazioneVerificaTaskSync.php"
download_one "custom/Espo/Custom/Hooks/Quote/SyncVerificaInstallazioneTask.php"
download_one "custom/Espo/Custom/Hooks/Task/ApplyVerificaInstallazioneEsito.php"
download_one "custom/Espo/Custom/Services/ContrattoStatiRules.php"
download_one "custom/Espo/Custom/Resources/metadata/entityDefs/Task.json"
download_one "custom/Espo/Custom/Resources/metadata/logicDefs/Task.json"
download_one "custom/Espo/Custom/Resources/layouts/Quote/detail.json"
download_one "custom/Espo/Custom/Resources/layouts/Task/detail.json"
download_one "custom/Espo/Custom/Resources/i18n/it_IT/Task.json"
download_one "tools/backfill-verifica-installazione-task.php"
download_one "tools/bonifica-invalido-data-installazione.php"
download_one "tools/patch-quote-enum-bonificati.php"
download_one "tools/patch-quote-verifica-installazione-meta.php"
download_one "tools/verify-regressions.php"

echo ">> patch IN-PLACE enum bonificati (niente overwrite Quote.json)"
php tools/patch-quote-enum-bonificati.php

echo ">> patch IN-PLACE solo campi verificaInstallazioneTask"
php tools/patch-quote-verifica-installazione-meta.php

php tools/verify-regressions.php --profile=quote-stati

php clear_cache.php
rm -rf data/cache/*
php rebuild.php

echo
echo "Deploy OK. Quote.json NON è stato sostituito per intero."
echo "1) php tools/bonifica-invalido-data-installazione.php --dry-run --limit=20"
echo "2) php tools/backfill-verifica-installazione-task.php --dry-run --limit=20"
