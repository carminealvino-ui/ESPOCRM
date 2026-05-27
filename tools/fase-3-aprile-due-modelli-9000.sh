#!/usr/bin/env bash
# =============================================================================
# Listino APRILE 2026 — due modelli Falcon 9.000 (MONO + MONO PLUS)
#
# cd ~/public_html/crm/mec-group
# curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/opportunity-globallogic-9999/tools/fase-3-aprile-due-modelli-9000.sh" | DRY_RUN=1 bash
# curl -fsSL ".../tools/fase-3-aprile-due-modelli-9000.sh" | bash
# =============================================================================
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${GITHUB_BRANCH:-cursor/opportunity-globallogic-9999}"
REPO="${GITHUB_REPOSITORY:-carminealvino-ui/ESPOCRM}"
RAW_BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

PRICE_BOOK_ID="${PRICE_BOOK_ID:-69ce7c1fa73049580}"
DATE_START="${DATE_START:-2026-04-01}"
DRY_RUN="${DRY_RUN:-0}"

cd "${CRM_ROOT}" || exit 1
mkdir -p tools database/data

curl -fsSL "${RAW_BASE}/tools/sync-listino-prodotti.php" -o tools/sync-listino-prodotti.php
curl -fsSL "${RAW_BASE}/database/data/listino-ariel-climatizzatori-2604-aprile-9000.csv" \
  -o database/data/listino-ariel-climatizzatori-2604-aprile-9000.csv

ARGS=(
  --csv=database/data/listino-ariel-climatizzatori-2604-aprile-9000.csv
  --price-book-id="${PRICE_BOOK_ID}"
  --date-start="${DATE_START}"
  --aliquota-iva=10
)

[[ "${DRY_RUN}" == "1" ]] && ARGS+=(--dry-run)

echo "=== Aprile 2026 — due modelli 9.000 su listino ${PRICE_BOOK_ID} ==="
echo "MONO 9000BTU: vigore solo fino al 30/04/2026"
php tools/sync-listino-prodotti.php "${ARGS[@]}"

echo ""
echo "Poi chiudere eventuali prezzi MONO 9000 su listini maggio+:"
echo "  curl -fsSL .../tools/fase-3d-chiudi-mono-9000-dopo-aprile.sh | bash"
