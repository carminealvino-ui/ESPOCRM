#!/usr/bin/env bash
# Deploy script sync listino prodotti (server produzione)
set -euo pipefail

BRANCH="${GITHUB_BRANCH:-cursor/opportunity-globallogic-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"

cd "$CRM_ROOT"

curl -fsSL "${BASE}/tools/sync-listino-prodotti.php" -o tools/sync-listino-prodotti.php
mkdir -p database/data
curl -fsSL "${BASE}/database/data/listino-ariel-climatizzatori-07052026.csv" \
  -o database/data/listino-ariel-climatizzatori-07052026.csv

echo "OK: tools/sync-listino-prodotti.php + CSV listino"
echo "Esempio dry-run:"
echo "  php tools/sync-listino-prodotti.php \\"
echo "    --csv=database/data/listino-ariel-climatizzatori-07052026.csv \\"
echo "    --price-book-name='ARIEL' \\"
echo "    --date-start=2026-05-07 \\"
echo "    --dry-run"
