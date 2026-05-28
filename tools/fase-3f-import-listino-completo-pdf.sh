#!/usr/bin/env bash
# =============================================================================
# FASE 3f — Import listino completo Ariel 07.05.26 (CSV da GitHub o da PDF)
#
# Listino: PRICE_BOOK_ID=07ce1b326cd314ca2 (ARIEL - 26-07-05)
#
# Default: scarica CSV già generato dal repo (NON serve pdftotext sul server).
# Solo con EXTRACT_FROM_PDF=1 serve poppler-utils (pdftotext) per rigenerare il CSV.
#
# Dry-run:
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/opportunity-globallogic-9999/tools/fase-3f-import-listino-completo-pdf.sh" | DRY_RUN=1 bash
#
# Apply:
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/opportunity-globallogic-9999/tools/fase-3f-import-listino-completo-pdf.sh" | bash
# =============================================================================
set -euo pipefail

SCRIPT_VERSION="2026-05-27-prezzo-codice-v4"

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${GITHUB_BRANCH:-cursor/opportunity-globallogic-9999}"
REPO="${GITHUB_REPOSITORY:-carminealvino-ui/ESPOCRM}"
RAW_BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

PRICE_BOOK_ID="${PRICE_BOOK_ID:-07ce1b326cd314ca2}"
DATE_START="${DATE_START:-2026-05-07}"
DRY_RUN="${DRY_RUN:-0}"
EXTRACT_FROM_PDF="${EXTRACT_FROM_PDF:-0}"
PDF_NAME="Listino Prodotti ARIEL ENERGIA 07.05.26.pdf"
CSV_OUT="database/data/listino-ariel-prodotti-07052026.csv"

cd "${CRM_ROOT}" || exit 1

mkdir -p tools database/data "database/listini"

echo "=== FASE 3f ${SCRIPT_VERSION} — CSV da GitHub (pdftotext NON richiesto) ==="
echo "=== Scarico script da GitHub (${BRANCH}) ==="
curl -fsSL "${RAW_BASE}/tools/sync-listino-prodotti.php" -o tools/sync-listino-prodotti.php

if [[ "${EXTRACT_FROM_PDF}" == "1" ]]; then
  curl -fsSL "${RAW_BASE}/tools/extract-listino-ariel-pdf.php" -o tools/extract-listino-ariel-pdf.php

  if [[ ! -f "database/listini/${PDF_NAME}" ]]; then
    echo "=== Scarico PDF listino ==="
    curl -fsSL "${RAW_BASE}/database/listini/${PDF_NAME// /%20}" \
      -o "database/listini/${PDF_NAME}" || {
      echo "ERRORE: PDF non presente — caricare database/listini/${PDF_NAME}" >&2
      exit 1
    }
  fi

  if ! command -v pdftotext >/dev/null 2>&1; then
    echo "ERRORE: EXTRACT_FROM_PDF=1 richiede pdftotext (pacchetto poppler-utils)." >&2
    echo "  Su hosting condiviso: usa il CSV da GitHub (default, senza EXTRACT_FROM_PDF)." >&2
    exit 1
  fi

  echo "=== Estrazione PDF → CSV (locale) ==="
  php tools/extract-listino-ariel-pdf.php \
    --pdf="database/listini/${PDF_NAME}" \
    --out="${CSV_OUT}"
else
  echo "=== Scarico CSV listino da GitHub (nessun pdftotext richiesto) ==="
  curl -fsSL "${RAW_BASE}/${CSV_OUT}" -o "${CSV_OUT}" || {
    echo "ERRORE: impossibile scaricare ${CSV_OUT} da GitHub." >&2
    echo "  Alternativa: EXTRACT_FROM_PDF=1 se sul server è installato pdftotext." >&2
    exit 1
  }
fi

if [[ ! -s "${CSV_OUT}" ]]; then
  echo "ERRORE: CSV vuoto o assente: ${CSV_OUT}" >&2
  exit 1
fi

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
echo "=== FINE FASE 3f — Listino completo ==="
