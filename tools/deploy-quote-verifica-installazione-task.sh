#!/usr/bin/env bash
# Deploy verifica installazione — SOLO file sicuri + patch IN-PLACE.
# VIETATO sovrascrivere: Quote.json, Task.json, layout, i18n (usare patch).
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

# === SAFE: solo PHP nuovi / tools / ContrattoStatiRules (logica feature) ===
download_one "custom/Espo/Custom/Services/QuoteInstallazioneVerificaTaskSync.php"
download_one "custom/Espo/Custom/Hooks/Quote/SyncVerificaInstallazioneTask.php"
download_one "custom/Espo/Custom/Hooks/Task/ApplyVerificaInstallazioneEsito.php"
download_one "custom/Espo/Custom/Services/ContrattoStatiRules.php"
download_one "tools/backfill-verifica-installazione-task.php"
download_one "tools/bonifica-invalido-data-installazione.php"
download_one "tools/patch-quote-enum-bonificati.php"
download_one "tools/patch-quote-verifica-installazione-meta.php"
download_one "tools/patch-task-verifica-installazione-meta.php"
download_one "tools/patch-layouts-verifica-installazione.php"
download_one "tools/verify-regressions.php"

echo
echo ">> PATCH in-place (niente overwrite metadata/layout)"
php tools/patch-quote-enum-bonificati.php
php tools/patch-quote-verifica-installazione-meta.php
php tools/patch-task-verifica-installazione-meta.php
php tools/patch-layouts-verifica-installazione.php

php tools/verify-regressions.php --profile=quote-stati

# Blocco se Quote.json ancora legacy
if grep -E '"In Attesa Documentazione"|"Draft"|"Presented"|"Canceled"|"Finanziamento Rifiutato"' \
  custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json; then
  echo "ERR: Quote.json ancora sporco dopo patch"
  exit 1
fi

php clear_cache.php
rm -rf data/cache
mkdir -p data/cache
php rebuild.php

echo
echo "Deploy OK (solo patch metadata/layout)."
echo "1) php tools/bonifica-invalido-data-installazione.php --dry-run --limit=20"
echo "2) php tools/backfill-verifica-installazione-task.php --dry-run --limit=20"
