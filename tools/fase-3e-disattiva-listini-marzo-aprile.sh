#!/usr/bin/env bash
# =============================================================================
# Disattiva listini ARIEL Marzo e Aprile 2026 (non più in vigore)
#
# cd ~/public_html/crm/mec-group
# curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/opportunity-globallogic-9999/tools/fase-3e-disattiva-listini-marzo-aprile.sh" | DRY_RUN=1 bash
# curl -fsSL ".../fase-3e-disattiva-listini-marzo-aprile.sh" | bash
# =============================================================================
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
DRY_RUN="${DRY_RUN:-0}"

PB_MARZO="${PB_MARZO:-69d4c2dce710dc14b}"
PB_APRILE="${PB_APRILE:-69ce7c1fa73049580}"

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

echo "=== Listini ARIEL PRIMA ==="
"${MYSQL_BIN}" --defaults-extra-file="${CNF}" "${DB_NAME}" -e "
SELECT id, name, status
FROM price_book
WHERE deleted = 0 AND UPPER(name) LIKE '%ARIEL%'
ORDER BY name;
"

if [[ "${DRY_RUN}" == "1" ]]; then
  echo ""
  echo "DRY_RUN=1 — per disattivare Marzo (${PB_MARZO}) e Aprile (${PB_APRILE}) rimuovere DRY_RUN=1"
  exit 0
fi

echo ""
echo "=== Imposto status = Inactive (Marzo + Aprile) ==="
"${MYSQL_BIN}" --defaults-extra-file="${CNF}" "${DB_NAME}" -e "
UPDATE price_book
SET status = 'Inactive', modified_at = NOW()
WHERE deleted = 0
  AND id IN ('${PB_MARZO}', '${PB_APRILE}');
SELECT ROW_COUNT() AS listini_disattivati;
"

echo ""
echo "=== Listini ARIEL DOPO (attesi: solo Maggio + 07/05 Active) ==="
"${MYSQL_BIN}" --defaults-extra-file="${CNF}" "${DB_NAME}" -e "
SELECT id, name, status
FROM price_book
WHERE deleted = 0 AND UPPER(name) LIKE '%ARIEL%'
ORDER BY name;
"

echo ""
echo "In Espo: Ctrl+F5 su Listini Prezzo. Il resolver opportunità ignora già status != Active."
