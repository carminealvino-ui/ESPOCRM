#!/usr/bin/env bash
# Emergenza: ripristina SOLO enum bonificati senza toccare altro.
set -euo pipefail
ROOT="${1:-$HOME/public_html/crm/mec-group}"
BRANCH="cursor/quote-verifica-installazione-task-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "$ROOT"
curl -fsSL "${BASE}/tools/patch-quote-enum-bonificati.php" -o /tmp/patch-quote-enum-bonificati.php
cp -f /tmp/patch-quote-enum-bonificati.php tools/patch-quote-enum-bonificati.php
php tools/patch-quote-enum-bonificati.php
php clear_cache.php
rm -rf data/cache/*
php rebuild.php
echo "Enum bonificati ripristinati."
