#!/usr/bin/env bash
# Emergenza: forza enum bonificati + cache flush totale.
set -euo pipefail
ROOT="${1:-$HOME/public_html/crm/mec-group}"
BRANCH="cursor/quote-verifica-installazione-task-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "$ROOT"

curl -fsSL "${BASE}/tools/patch-quote-enum-bonificati.php" -o /tmp/patch-quote-enum-bonificati.php
cp -f /tmp/patch-quote-enum-bonificati.php tools/patch-quote-enum-bonificati.php

php tools/patch-quote-enum-bonificati.php

# Verifica immediata sul FILE (prima della cache)
if grep -E '"In Attesa Documentazione"|"Draft"|"Presented"|"Canceled"|"Finanziamento Rifiutato"' \
  custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json; then
  echo "ERR: file ancora sporco dopo patch"
  exit 1
fi

echo "File Quote.json PULITO."

rm -rf data/cache
mkdir -p data/cache
php clear_cache.php || true
php rebuild.php

echo
echo "FATTO. Ora nel browser: Ctrl+Shift+R (hard refresh)."
echo "Se vedi ancora stati sporchi, apri in finestra anonima."
