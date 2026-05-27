#!/usr/bin/env bash
# =============================================================================
# FASE 1 — Audit listino Ariel 07/05/2026 (SOLO LETTURA, nessun UPDATE)
# Server: telcalli@s4409 — root CRM ~/public_html/crm/mec-group
#
# Uso (copia-incolla intero blocco sul server, oppure):
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/opportunity-globallogic-9999/tools/run-fase-1-audit.sh" -o /tmp/run-fase-1-audit.sh && bash /tmp/run-fase-1-audit.sh
# =============================================================================
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${GITHUB_BRANCH:-cursor/opportunity-globallogic-9999}"
REPO="${GITHUB_REPOSITORY:-carminealvino-ui/ESPOCRM}"
RAW_BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

# --- 0) Directory progetto ---
cd "${CRM_ROOT}" || {
  echo "ERRORE: directory non trovata: ${CRM_ROOT}" >&2
  exit 1
}

if [[ ! -f "data/config-internal.php" ]]; then
  echo "ERRORE: manca data/config-internal.php in $(pwd)" >&2
  exit 1
fi

# --- 1) Credenziali DB da config-internal.php (stesso metodo usato in produzione) ---
DB_USER="$(sed -n "s/.*'user' => '\([^']*\)'.*/\1/p" data/config-internal.php | head -1)"
DB_PASS="$(sed -n "s/.*'password' => '\([^']*\)'.*/\1/p" data/config-internal.php | head -1)"
DB_NAME="$(sed -n "s/.*'dbname' => '\([^']*\)'.*/\1/p" data/config-internal.php | head -1)"
DB_HOST="$(sed -n "s/.*'host' => '\([^']*\)'.*/\1/p" data/config-internal.php | head -1)"

DB_NAME="${DB_NAME:-telcalli_espo}"
DB_HOST="${DB_HOST:-localhost}"

if [[ -z "${DB_USER}" || -z "${DB_PASS}" ]]; then
  echo "ERRORE: impossibile leggere user/password da data/config-internal.php" >&2
  exit 1
fi

if command -v mariadb >/dev/null 2>&1; then
  MYSQL_BIN="mariadb"
elif command -v mysql >/dev/null 2>&1; then
  MYSQL_BIN="mysql"
else
  echo "ERRORE: né mariadb né mysql nel PATH" >&2
  exit 1
fi

CNF="$(mktemp)"
chmod 600 "${CNF}"
trap 'rm -f "${CNF}"' EXIT

printf '[client]\nhost=%s\nuser=%s\npassword=%s\ndatabase=%s\n' \
  "${DB_HOST}" "${DB_USER}" "${DB_PASS}" "${DB_NAME}" > "${CNF}"

echo "=== FASE 1 — Audit listino (DB: ${DB_NAME} @ ${DB_HOST}) ==="
echo "=== Attesi Falcon: listino 3590,91 escl. (3950 IVI) | codice 2681,82 escl. (2950 IVI) ==="
echo ""

# --- 2) B) Listini ARIEL ---
echo "=== B) Listini Price Book ARIEL ==="
"${MYSQL_BIN}" --defaults-extra-file="${CNF}" "${DB_NAME}" -e "
SELECT id, name, date_start, date_end, status
FROM price_book
WHERE deleted = 0
  AND UPPER(name) LIKE '%ARIEL%'
ORDER BY name;
"

echo ""
echo "=== B2) Listini ARIEL con riferimento 05/2026 nel nome ==="
"${MYSQL_BIN}" --defaults-extra-file="${CNF}" "${DB_NAME}" -e "
SELECT id, name, date_start, date_end
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

# --- 3) C) Prodotto Falcon ---
echo ""
echo "=== C) Prodotto Falcon (part_number 00.02.95.0) ==="
"${MYSQL_BIN}" --defaults-extra-file="${CNF}" "${DB_NAME}" -e "
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
echo "=== C2) Check Falcon vs valori attesi (IVA escl.) ==="
"${MYSQL_BIN}" --defaults-extra-file="${CNF}" "${DB_NAME}" -e "
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

# --- 4) D) ProductPrice Falcon su tutti i listini ARIEL ---
echo ""
echo "=== D) Prezzi Falcon (product_price) su listini ARIEL ==="
"${MYSQL_BIN}" --defaults-extra-file="${CNF}" "${DB_NAME}" -e "
SELECT
    pb.name AS listino,
    pb.id AS price_book_id,
    p.part_number,
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

# --- 5) E) Anomalie ---
echo ""
echo "=== E) Codici articolo duplicati ==="
"${MYSQL_BIN}" --defaults-extra-file="${CNF}" "${DB_NAME}" -e "
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

# --- 6) F) Opportunità recenti ARIEL ---
echo ""
echo "=== F) Ultime 15 opportunità ARIEL (listino collegato) ==="
"${MYSQL_BIN}" --defaults-extra-file="${CNF}" "${DB_NAME}" -e "
SELECT
    o.id,
    o.name,
    o.price_book_id,
    o.price_book_name,
    o.prezzo_listino_iva_esclusa,
    o.prezzo_codice_iva_esclusa,
    o.data_opportunit,
    o.created_at
FROM opportunity o
WHERE o.deleted = 0
  AND (
      UPPER(IFNULL(o.price_book_name, '')) LIKE '%ARIEL%'
      OR UPPER(IFNULL(o.azienda, '')) LIKE '%ARIEL%'
      OR UPPER(IFNULL(o.product_brand_name, '')) LIKE '%ARIEL%'
  )
ORDER BY o.created_at DESC
LIMIT 15;
"

echo ""
echo "=== FINE FASE 1 (nessuna modifica al DB) ==="
echo "Invia l'output completo per passare alla Fase 2 (configurazione Price Book 07/05/2026)."
echo ""

# --- 7) Opzionale: audit PHP Espo (se bootstrap presente) ---
if [[ -f "bootstrap.php" ]] && command -v php >/dev/null 2>&1; then
  mkdir -p tools
  if [[ ! -f "tools/fase-1-audit-listino.php" ]]; then
    echo "=== Scarico tools/fase-1-audit-listino.php da GitHub ==="
    curl -fsSL "${RAW_BASE}/tools/fase-1-audit-listino.php" -o tools/fase-1-audit-listino.php
  fi
  echo "=== Audit PHP (riepilogo) ==="
  php tools/fase-1-audit-listino.php "${CRM_ROOT}" || true
fi
