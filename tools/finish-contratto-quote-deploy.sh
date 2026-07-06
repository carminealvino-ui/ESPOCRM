#!/usr/bin/env bash
# Completa deploy contratto se interrotto dalla verifica formula (falso positivo).
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/fix-contratto-quote-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}" || exit 1

curl -fsSL "${BASE}/tools/verify-contratto-quote-deploy.php?t=$(date +%s)" -o tools/verify-contratto-quote-deploy.php
curl -fsSL "${BASE}/tools/backfill-quote-totale-provvigioni.php?t=$(date +%s)" -o tools/backfill-quote-totale-provvigioni.php

echo "=== Backfill totaleProvvigioni ==="
php tools/backfill-quote-totale-provvigioni.php

echo ""
echo "=== Cache ==="
php clear_cache.php
rm -rf data/cache/* 2>/dev/null || true

echo ""
echo "=== Verifica ==="
php tools/verify-contratto-quote-deploy.php

echo ""
echo "Fatto. Ctrl+Shift+R nel browser."
