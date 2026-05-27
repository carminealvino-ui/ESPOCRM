#!/usr/bin/env bash
# =============================================================================
# Verifica quali listini ARIEL sono Active in CRM
#
# cd ~/public_html/crm/mec-group
# curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/opportunity-globallogic-9999/tools/verifica-listini-ariel-attivi.sh" | bash
# =============================================================================
set -euo pipefail

cd "${CRM_ROOT:-$HOME/public_html/crm/mec-group}" || exit 1

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

echo "=== Listini ARIEL (tutti) ==="
"${MYSQL_BIN}" --defaults-extra-file="${CNF}" "${DB_NAME}" -e "
SELECT id, name, status,
  CASE WHEN status = 'Active' THEN 'USARE' ELSE 'NON ATTIVO' END AS uso
FROM price_book
WHERE deleted = 0 AND UPPER(name) LIKE '%ARIEL%'
ORDER BY name;
"

echo ""
echo "Listino climatizzatori 07/05/2026 atteso: 07ce1b326cd314ca2 (Active)"
