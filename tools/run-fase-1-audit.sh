#!/usr/bin/env bash
# =============================================================================
# FASE 1 — Audit listino Ariel 07/05/2026 (SOLO LETTURA)
# Produzione: telcalli_espo — colonne allineate allo schema reale (no date_start su price_book)
#
# Esecuzione consigliata (non serve file in tools/):
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/opportunity-globallogic-9999/tools/run-fase-1-audit.sh" | bash
# =============================================================================
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"

cd "${CRM_ROOT}" || {
  echo "ERRORE: directory non trovata: ${CRM_ROOT}" >&2
  exit 1
}

if [[ ! -f "data/config-internal.php" ]]; then
  echo "ERRORE: manca data/config-internal.php in $(pwd)" >&2
  exit 1
fi

DB_PASS="$(sed -n "s/.*'password' => '\([^']*\)'.*/\1/p" data/config-internal.php | head -1)"
DB_USER="$(sed -n "s/.*'user' => '\([^']*\)'.*/\1/p" data/config-internal.php | head -1)"
DB_NAME="$(sed -n "s/.*'dbname' => '\([^']*\)'.*/\1/p" data/config-internal.php | head -1)"
DB_HOST="$(sed -n "s/.*'host' => '\([^']*\)'.*/\1/p" data/config-internal.php | head -1)"
DB_NAME="${DB_NAME:-telcalli_espo}"
DB_HOST="${DB_HOST:-localhost}"

if [[ -z "${DB_USER}" || -z "${DB_PASS}" ]]; then
  echo "ERRORE: user/password non letti da config-internal.php" >&2
  exit 1
fi

if command -v mariadb >/dev/null 2>&1; then
  MYSQL_BIN="mariadb"
else
  MYSQL_BIN="mysql"
fi

CNF="$(mktemp)"
chmod 600 "${CNF}"
trap 'rm -f "${CNF}"' EXIT
printf '[client]\nhost=%s\nuser=%s\npassword=%s\ndatabase=%s\n' \
  "${DB_HOST}" "${DB_USER}" "${DB_PASS}" "${DB_NAME}" > "${CNF}"

run_sql() {
  "${MYSQL_BIN}" --defaults-extra-file="${CNF}" "${DB_NAME}" -e "$1"
}

echo "=== FASE 1 — Audit listino Ariel (DB: ${DB_NAME}) ==="
echo "=== Attesi Falcon IVA escl.: listino 3590,91 | codice 2681,82 ==="
echo ""

echo "=== A) Colonne tabelle (riferimento schema produzione) ==="
run_sql "SHOW COLUMNS FROM price_book;"
echo ""
run_sql "SHOW COLUMNS FROM product_price LIKE 'date%';"
echo ""

echo "=== B) Listini ARIEL (price_book: id, name, status) ==="
run_sql "
SELECT id, name, status
FROM price_book
WHERE deleted = 0
  AND UPPER(name) LIKE '%ARIEL%'
ORDER BY name;
"

echo ""
echo "=== B2) Listini ARIEL — nome con 05 / 2026 ==="
run_sql "
SELECT id, name, status
FROM price_book
WHERE deleted = 0
  AND UPPER(name) LIKE '%ARIEL%'
  AND (
      UPPER(name) LIKE '%05%'
      OR UPPER(name) LIKE '%MAGG%'
      OR UPPER(name) LIKE '%2026%'
  )
ORDER BY name;
"

echo ""
echo "=== C) Prodotto Falcon ==="
run_sql "
SELECT
    id,
    name,
    part_number,
    list_price,
    prezzo_codice,
    ROUND(list_price * 1.10, 2) AS listino_ivi_ricalcolato,
    ROUND(prezzo_codice * 1.10, 2) AS codice_ivi_ricalcolato
FROM product
WHERE deleted = 0
  AND (
      part_number = '00.02.95.0'
      OR UPPER(name) LIKE '%FALCON%9%'
  )
ORDER BY part_number, name;
"

echo ""
echo "=== C2) Check Falcon (vs 3590,91 / 2681,82 IVA escl.) ==="
run_sql "
SELECT
    id,
    name,
    part_number,
    list_price,
    prezzo_codice,
    CASE WHEN ABS(IFNULL(list_price, 0) - 3590.91) < 0.02 THEN 'OK' ELSE 'DA ALLINEARE' END AS check_listino,
    CASE WHEN ABS(IFNULL(prezzo_codice, 0) - 2681.82) < 0.02 THEN 'OK' ELSE 'DA ALLINEARE' END AS check_codice
FROM product
WHERE deleted = 0
  AND part_number = '00.02.95.0';
"

echo ""
echo "=== D) product_price Falcon su listini ARIEL (date_start su riga prezzo) ==="
run_sql "
SELECT
    pb.name AS listino,
    pb.id AS price_book_id,
    pp.price AS price_iva_escl,
    ROUND(pp.price * 1.10, 2) AS price_ivi,
    pp.date_start,
    pp.date_end,
    pp.status,
    CASE WHEN ABS(IFNULL(pp.price, 0) - 3590.91) < 0.02 THEN 'OK' ELSE 'DA ALLINEARE' END AS check_listino
FROM product_price pp
INNER JOIN product p ON p.id = pp.product_id AND p.deleted = 0
INNER JOIN price_book pb ON pb.id = pp.price_book_id AND pb.deleted = 0
WHERE pp.deleted = 0
  AND p.part_number = '00.02.95.0'
  AND UPPER(pb.name) LIKE '%ARIEL%'
ORDER BY pb.name, pp.date_start DESC;
"

echo ""
echo "=== E) Codici articolo duplicati ==="
run_sql "
SELECT part_number, COUNT(*) AS n
FROM product
WHERE deleted = 0
  AND part_number IS NOT NULL
  AND TRIM(part_number) <> ''
GROUP BY part_number
HAVING n > 1
ORDER BY n DESC
LIMIT 20;
"

echo ""
echo "=== F) Ultime 15 opportunità ARIEL (JOIN price_book, no price_book_name) ==="
run_sql "
SELECT
    o.id,
    o.name,
    o.price_book_id,
    pb.name AS price_book_name,
    o.prezzo_listino_iva_esclusa,
    o.prezzo_codice_iva_esclusa,
    o.data_opportunit,
    o.created_at
FROM opportunity o
LEFT JOIN price_book pb ON pb.id = o.price_book_id AND pb.deleted = 0
WHERE o.deleted = 0
  AND (
      UPPER(IFNULL(pb.name, '')) LIKE '%ARIEL%'
      OR UPPER(IFNULL(o.azienda, '')) LIKE '%ARIEL%'
  )
ORDER BY o.created_at DESC
LIMIT 15;
"

echo ""
echo "=== FINE FASE 1 — incolla tutto l'output per la Fase 2 ==="
