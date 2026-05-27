#!/usr/bin/env bash
# =============================================================================
# FASE 2 — Crea listino Sales Pack ARIEL in vigore dal 07/05/2026
# SOLO inserimento price_book (nessun prodotto ancora — Fase 3)
#
# Esecuzione (copia una riga):
#   cd ~/public_html/crm/mec-group && curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/opportunity-globallogic-9999/tools/fase-2-crea-listino-ariel-070526.sh" | bash
#
# Anteprima senza scrivere:
#   ... | DRY_RUN=1 bash
# =============================================================================
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
DRY_RUN="${DRY_RUN:-0}"

# Nome listino in Espo (coerente con Marzo/Aprile/Maggio 2026)
PB_NAME="${PB_NAME:-ARIEL - 26-07-05 (Climatizzatori 07/05/2026)}"

# Copia impostazioni da listino Maggio 2026 (audit Fase 1)
TEMPLATE_PB_ID="${TEMPLATE_PB_ID:-6a043018dc22acf33}"

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

run_sql() {
  "${MYSQL_BIN}" --defaults-extra-file="${CNF}" "${DB_NAME}" -N -e "$1"
}

echo "=== FASE 2 — Crea Price Book: ${PB_NAME} ==="
echo "=== Template: ${TEMPLATE_PB_ID} (ARIEL Maggio 2026) ==="

EXISTING="$(run_sql "
SELECT id FROM price_book
WHERE deleted = 0 AND name = '${PB_NAME}'
LIMIT 1;
")"

if [[ -n "${EXISTING}" ]]; then
  echo "Già presente: id=${EXISTING} — nessuna azione."
  run_sql "
SELECT id, name, status, is_tax_inclusive FROM price_book WHERE id = '${EXISTING}';
"
  exit 0
fi

TEMPLATE_OK="$(run_sql "SELECT COUNT(*) FROM price_book WHERE id = '${TEMPLATE_PB_ID}' AND deleted = 0;")"
if [[ "${TEMPLATE_OK}" != "1" ]]; then
  echo "ERRORE: template listino ${TEMPLATE_PB_ID} non trovato." >&2
  exit 1
fi

NEW_ID="$(php -r 'echo substr(bin2hex(random_bytes(9)), 0, 17);')"

echo "Nuovo id listino: ${NEW_ID}"

if [[ "${DRY_RUN}" == "1" ]]; then
  echo "DRY_RUN=1 — INSERT non eseguito."
  run_sql "
SELECT id, name, status, is_tax_inclusive
FROM price_book WHERE id = '${TEMPLATE_PB_ID}';
"
  exit 0
fi

"${MYSQL_BIN}" --defaults-extra-file="${CNF}" "${DB_NAME}" -e "
INSERT INTO price_book (
    id,
    name,
    description,
    status,
    is_tax_inclusive,
    deleted,
    created_at,
    modified_at,
    parent_price_book_id,
    created_by_id,
    modified_by_id,
    assigned_user_id
)
SELECT
    '${NEW_ID}',
    '${PB_NAME}',
    'Listino climatizzatori Ariel Energia — comunicato in vigore dal 07/05/2026 (PDF GitHub).',
    status,
    is_tax_inclusive,
    0,
    NOW(),
    NOW(),
    parent_price_book_id,
    created_by_id,
    modified_by_id,
    assigned_user_id
FROM price_book
WHERE id = '${TEMPLATE_PB_ID}'
LIMIT 1;
"

echo ""
echo "=== Verifica ==="
"${MYSQL_BIN}" --defaults-extra-file="${CNF}" "${DB_NAME}" -e "
SELECT id, name, status, is_tax_inclusive, created_at
FROM price_book
WHERE id = '${NEW_ID}';
"

echo ""
echo "=== FINE FASE 2 ==="
echo "Salva questo id per Fase 3:"
echo "  PRICE_BOOK_ID=${NEW_ID}"
echo ""
echo "In Amministrazione → Rebuild non obbligatorio per price_book."
echo "Prossimo: Fase 3 — sync prodotti/prezzi (product_price date_start = 2026-05-07)."
