#!/usr/bin/env bash
# =============================================================================
# FALCON MONO 9000BTU (senza PLUS) — valido fino al 30/04/2026 (1° maggio solo PLUS)
#
# Chiude le righe product_price errate su listini Maggio e 07/05 create dal sync precedente.
#
# cd ~/public_html/crm/mec-group
# curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/opportunity-globallogic-9999/tools/fase-3d-chiudi-mono-9000-dopo-aprile.sh" | DRY_RUN=1 bash
# curl -fsSL ".../fase-3d-chiudi-mono-9000-dopo-aprile.sh" | bash
# =============================================================================
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
DRY_RUN="${DRY_RUN:-0}"

# Listini da maggio in poi (no MONO 9000 senza PLUS)
PB_MAGGIO="${PB_MAGGIO:-6a043018dc22acf33}"
PB_LUGLIO="${PB_LUGLIO:-07ce1b326cd314ca2}"
DATA_FINE="${DATA_FINE:-2026-04-30}"

cd "${CRM_ROOT}" || exit 1

DB_PASS="$(sed -n "s/.*'password' => '\([^']*\)'.*/\1/p" data/config-internal.php | head -1)"
DB_USER="$(sed -n "s/.*'user' => '\([^']*\)'.*/\1/p" data/config-internal.php | head -1)"
DB_NAME="$(sed -n "s/.*'dbname' => '\([^']*\)'.*/\1/p" data/config-internal.php | head -1)"
DB_HOST="$(sed -n "s/.*'host' => '\([^']*\)'.*/\1/p" data/config-internal.php | head -1)"
DB_NAME="${DB_NAME:-telcalli_espo}"
DB_HOST="${DB_HOST:-localhost}"

MYSQL_BIN="mariadb"
command -v mariadb >/dev/null 2>&1 || MYSQL_BIN="mysql"

CNF="$(mktemp)"
chmod 600 "${CNF}"
trap 'rm -f "${CNF}"' EXIT
printf '[client]\nhost=%s\nuser=%s\npassword=%s\ndatabase=%s\n' \
  "${DB_HOST}" "${DB_USER}" "${DB_PASS}" "${DB_NAME}" > "${CNF}"

PRODUCT_NAME="ARIEL - CLIMATIZZATORI - FALCON MONO 9000BTU"

echo "=== FALCON MONO 9000BTU — vigore fino al ${DATA_FINE} (escluso da 1 maggio) ==="
echo ""

echo "=== Prima: righe attive su listini Maggio / 07-05 ==="
"${MYSQL_BIN}" --defaults-extra-file="${CNF}" "${DB_NAME}" -e "
SELECT pp.id, pb.name AS listino, p.name, pp.price, pp.date_start, pp.date_end, pp.status
FROM product_price pp
JOIN product p ON p.id = pp.product_id AND p.deleted = 0
JOIN price_book pb ON pb.id = pp.price_book_id AND pb.deleted = 0
WHERE pp.deleted = 0
  AND p.name = '${PRODUCT_NAME}'
  AND pp.price_book_id IN ('${PB_MAGGIO}', '${PB_LUGLIO}')
ORDER BY pb.name;
"

if [[ "${DRY_RUN}" == "1" ]]; then
  echo ""
  echo "DRY_RUN=1 — nessun UPDATE eseguito."
  echo "Rimuovere DRY_RUN=1 per impostare date_end=${DATA_FINE} e status=Inactive."
  exit 0
fi

echo ""
echo "=== Chiusura righe (date_end=${DATA_FINE}) ==="
"${MYSQL_BIN}" --defaults-extra-file="${CNF}" "${DB_NAME}" -e "
UPDATE product_price pp
INNER JOIN product p ON p.id = pp.product_id AND p.deleted = 0
SET
    pp.date_end = '${DATA_FINE}',
    pp.status = 'Inactive',
    pp.modified_at = NOW()
WHERE pp.deleted = 0
  AND p.name = '${PRODUCT_NAME}'
  AND pp.price_book_id IN ('${PB_MAGGIO}', '${PB_LUGLIO}')
  AND (pp.date_end IS NULL OR pp.date_end > '${DATA_FINE}');
SELECT ROW_COUNT() AS righe_chiuse;
"

echo ""
echo "=== Dopo ==="
"${MYSQL_BIN}" --defaults-extra-file="${CNF}" "${DB_NAME}" -e "
SELECT pb.name, pp.price, pp.date_start, pp.date_end, pp.status
FROM product_price pp
JOIN product p ON p.id = pp.product_id
JOIN price_book pb ON pb.id = pp.price_book_id
WHERE pp.deleted = 0 AND p.name = '${PRODUCT_NAME}'
  AND pp.price_book_id IN ('${PB_MAGGIO}', '${PB_LUGLIO}');
"

echo ""
echo "=== FINE — Su listino APRILE resta valido il MONO 9000; da maggio usare solo MONO PLUS ==="
