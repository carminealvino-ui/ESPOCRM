#!/usr/bin/env bash
# =============================================================================
# FASE 3b — Crea solo Falcon 12k / 18k / 24k mancanti (con nome BRAND - CATEGORIA - DENOMINAZIONE)
#
# cd ~/public_html/crm/mec-group
# curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/opportunity-globallogic-9999/tools/fase-3b-crea-falcon-mancanti.sh" | bash
# =============================================================================
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${GITHUB_BRANCH:-cursor/opportunity-globallogic-9999}"
REPO="${GITHUB_REPOSITORY:-carminealvino-ui/ESPOCRM}"
RAW_BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
PRICE_BOOK_ID="${PRICE_BOOK_ID:-07ce1b326cd314ca2}"

cd "${CRM_ROOT}" || exit 1
mkdir -p tools database/data

curl -fsSL "${RAW_BASE}/tools/sync-listino-prodotti.php" -o tools/sync-listino-prodotti.php
curl -fsSL "${RAW_BASE}/database/data/listino-ariel-climatizzatori-07052026.csv" \
  -o database/data/listino-ariel-climatizzatori-07052026.csv

TMP_CSV="$(mktemp)"
grep -E 'MONO PLUS 12000|MONO PLUS 18000|MONO PLUS 24000' database/data/listino-ariel-climatizzatori-07052026.csv > "${TMP_CSV}" || true
head -1 database/data/listino-ariel-climatizzatori-07052026.csv | cat - "${TMP_CSV}" > "${TMP_CSV}.full"
mv "${TMP_CSV}.full" "${TMP_CSV}"

echo "=== FASE 3b — Crea/aggiorna Falcon 12k, 18k, 24k ==="
php tools/sync-listino-prodotti.php \
  --csv="${TMP_CSV}" \
  --price-book-id="${PRICE_BOOK_ID}" \
  --date-start=2026-05-07 \
  --aliquota-iva=10

rm -f "${TMP_CSV}"
echo "=== FINE FASE 3b ==="
