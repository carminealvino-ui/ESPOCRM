#!/usr/bin/env bash
# =============================================================================
# FASE 3 — Allinea prodotti + product_price sul listino 07/05/2026
#
# Listino creato in Fase 2:
#   PRICE_BOOK_ID=07ce1b326cd314ca2
#   ARIEL - 26-07-05 (Climatizzatori 07/05/2026)
#
# 1) Dry-run (nessuna scrittura):
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/opportunity-globallogic-9999/tools/fase-3-sync-listino-ariel-070526.sh" | DRY_RUN=1 bash
#
# 2) Applica:
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/opportunity-globallogic-9999/tools/fase-3-sync-listino-ariel-070526.sh" | bash
# =============================================================================
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${GITHUB_BRANCH:-cursor/opportunity-globallogic-9999}"
REPO="${GITHUB_REPOSITORY:-carminealvino-ui/ESPOCRM}"
RAW_BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

PRICE_BOOK_ID="${PRICE_BOOK_ID:-07ce1b326cd314ca2}"
DATE_START="${DATE_START:-2026-05-07}"
DRY_RUN="${DRY_RUN:-0}"

cd "${CRM_ROOT}" || exit 1

mkdir -p tools database/data

echo "=== Scarico script e CSV da GitHub (${BRANCH}) ==="
curl -fsSL "${RAW_BASE}/tools/sync-listino-prodotti.php" -o tools/sync-listino-prodotti.php
curl -fsSL "${RAW_BASE}/database/data/listino-ariel-climatizzatori-07052026.csv" \
  -o database/data/listino-ariel-climatizzatori-07052026.csv

if [[ ! -f "bootstrap.php" ]]; then
  echo "ERRORE: bootstrap.php non trovato in $(pwd)" >&2
  exit 1
fi

ARGS=(
  --csv=database/data/listino-ariel-climatizzatori-07052026.csv
  --price-book-id="${PRICE_BOOK_ID}"
  --date-start="${DATE_START}"
  --aliquota-iva=10
)

if [[ "${NO_CREATE:-0}" == "1" ]]; then
  ARGS+=(--no-create-missing)
  echo "(solo aggiornamento prodotti esistenti — NO_CREATE=1)"
fi

if [[ "${DRY_RUN}" == "1" ]]; then
  ARGS+=(--dry-run)
  echo "=== FASE 3 — DRY RUN (nessun salvataggio) ==="
else
  echo "=== FASE 3 — APPLY (scrive su DB) ==="
fi

echo "Listino: ${PRICE_BOOK_ID} | vigore product_price: ${DATE_START}"
echo ""

php tools/sync-listino-prodotti.php "${ARGS[@]}"

echo ""
echo "=== Verifica SQL (Falcon MONO PLUS) ==="

DB_PASS="$(sed -n "s/.*'password' => '\([^']*\)'.*/\1/p" data/config-internal.php | head -1)"
DB_USER="$(sed -n "s/.*'user' => '\([^']*\)'.*/\1/p" data/config-internal.php | head -1)"
DB_NAME="$(sed -n "s/.*'dbname' => '\([^']*\)'.*/\1/p" data/config-internal.php | head -1)"
DB_HOST="$(sed -n "s/.*'host' => '\([^']*\)'.*/\1/p" data/config-internal.php | head -1)"
DB_NAME="${DB_NAME:-telcalli_espo}"
DB_HOST="${DB_HOST:-localhost}"

CNF="$(mktemp)"
chmod 600 "${CNF}"
trap 'rm -f "${CNF}"' EXIT
printf '[client]\nhost=%s\nuser=%s\npassword=%s\ndatabase=%s\n' \
  "${DB_HOST}" "${DB_USER}" "${DB_PASS}" "${DB_NAME}" > "${CNF}"

MYSQL_BIN="mariadb"
command -v mariadb >/dev/null 2>&1 || MYSQL_BIN="mysql"

"${MYSQL_BIN}" --defaults-extra-file="${CNF}" "${DB_NAME}" -e "
SELECT id, name, denominazione, brand_name, category_name, list_price, prezzo_codice
FROM product
WHERE deleted = 0
  AND name = 'ARIEL - CLIMATIZZATORI - FALCON MONO PLUS 9000BTU';
"

"${MYSQL_BIN}" --defaults-extra-file="${CNF}" "${DB_NAME}" -e "
SELECT pb.name, pp.price, pp.date_start, pp.status
FROM product_price pp
JOIN product p ON p.id = pp.product_id AND p.deleted = 0
JOIN price_book pb ON pb.id = pp.price_book_id
WHERE pp.deleted = 0
  AND pp.price_book_id = '${PRICE_BOOK_ID}'
  AND p.name = 'ARIEL - CLIMATIZZATORI - FALCON MONO PLUS 9000BTU';
"

echo ""
echo "Attesi dopo APPLY (solo FALCON MONO PLUS — da maggio niente MONO 9000 senza PLUS):"
echo "  MONO PLUS 9000: list_price 3590,91 | prezzo_codice 2681,82 | product_price 3950 IVI"
echo "Regola vigore: vedi database/2026-05-27-falcon-plus-vigore-listini.sql"
echo ""
echo "=== FINE FASE 3 ==="
