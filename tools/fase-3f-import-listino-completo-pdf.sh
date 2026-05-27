#!/usr/bin/env bash
# =============================================================================
# FASE 3f — Estrae CSV dal PDF listino 07.05.26 e sincronizza tutti i prodotti
#
# Listino: PRICE_BOOK_ID=07ce1b326cd314ca2 (ARIEL - 26-07-05)
#
# Dry-run:
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/opportunity-globallogic-9999/tools/fase-3f-import-listino-completo-pdf.sh" | DRY_RUN=1 bash
#
# Apply:
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/opportunity-globallogic-9999/tools/fase-3f-import-listino-completo-pdf.sh" | bash
# =============================================================================
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${GITHUB_BRANCH:-cursor/opportunity-globallogic-9999}"
REPO="${GITHUB_REPOSITORY:-carminealvino-ui/ESPOCRM}"
RAW_BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

PRICE_BOOK_ID="${PRICE_BOOK_ID:-07ce1b326cd314ca2}"
DATE_START="${DATE_START:-2026-05-07}"
DRY_RUN="${DRY_RUN:-0}"
PDF_NAME="Listino Prodotti ARIEL ENERGIA 07.05.26.pdf"
CSV_OUT="database/data/listino-ariel-prodotti-07052026.csv"

cd "${CRM_ROOT}" || exit 1

mkdir -p tools database/data "database/listini"

echo "=== Scarico script da GitHub (${BRANCH}) ==="
curl -fsSL "${RAW_BASE}/tools/extract-listino-ariel-pdf.php" -o tools/extract-listino-ariel-pdf.php
curl -fsSL "${RAW_BASE}/tools/sync-listino-prodotti.php" -o tools/sync-listino-prodotti.php

if [[ ! -f "database/listini/${PDF_NAME}" ]]; then
  echo "=== Scarico PDF listino ==="
  curl -fsSL "${RAW_BASE}/database/listini/${PDF_NAME// /%20}" \
    -o "database/listini/${PDF_NAME}" || {
    echo "ERRORE: PDF non presente in database/listini/ — caricare ${PDF_NAME}" >&2
    exit 1
  }
fi

if ! command -v pdftotext >/dev/null 2>&1; then
  echo "ERRORE: installare poppler-utils (pdftotext) sul server" >&2
  exit 1
fi

echo "=== Estrazione PDF → CSV ==="
php tools/extract-listino-ariel-pdf.php \
  --pdf="database/listini/${PDF_NAME}" \
  --out="${CSV_OUT}"

ROWS="$(($(wc -l < "${CSV_OUT}") - 1))"
echo "Righe CSV: ${ROWS}"

if [[ ! -f "bootstrap.php" ]]; then
  echo "ERRORE: bootstrap.php non trovato in $(pwd)" >&2
  exit 1
fi

ARGS=(
  --csv="${CSV_OUT}"
  --price-book-id="${PRICE_BOOK_ID}"
  --date-start="${DATE_START}"
  --aliquota-iva=10
)

if [[ "${NO_CREATE:-0}" == "1" ]]; then
  ARGS+=(--no-create-missing)
fi

if [[ "${DRY_RUN}" == "1" ]]; then
  ARGS+=(--dry-run)
  echo "=== SYNC — DRY RUN ==="
else
  echo "=== SYNC — APPLY (${ROWS} prodotti) ==="
fi

php tools/sync-listino-prodotti.php "${ARGS[@]}"

echo ""
echo "=== FINE FASE 3f — Listino completo da PDF ==="
