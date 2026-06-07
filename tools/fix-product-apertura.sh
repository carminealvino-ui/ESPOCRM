#!/usr/bin/env bash
# Fix apertura scheda Product: schema DB + metadata senza view JS custom.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/product-prezzo-validita-9999/tools/fix-product-apertura.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/product-prezzo-validita-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

echo "=== Fix apertura Product → ${CRM_ROOT} ==="

curl -fsSL "${BASE}/tools/deploy-product-prezzo-validita.sh?t=$(date +%s)" | CRM_ROOT="${CRM_ROOT}" BRANCH="${BRANCH}" bash

SQL_FILE="${CRM_ROOT}/database/2026-06-07-product-schema-completa.sql"
mkdir -p "$(dirname "${SQL_FILE}")"
curl -fsSL -o "${SQL_FILE}" "${BASE}/database/2026-06-07-product-schema-completa.sql?t=$(date +%s)"

if command -v mysql >/dev/null 2>&1 && [[ -f "${CRM_ROOT}/data/config-internal.php" ]]; then
  echo "Tentativo import SQL schema product (mysql)..."
  DB_NAME=$(php -r "
    \$c = include '${CRM_ROOT}/data/config-internal.php';
    echo \$c['database']['dbname'] ?? '';
  " 2>/dev/null || true)
  DB_USER=$(php -r "
    \$c = include '${CRM_ROOT}/data/config-internal.php';
    echo \$c['database']['user'] ?? '';
  " 2>/dev/null || true)
  DB_PASS=$(php -r "
    \$c = include '${CRM_ROOT}/data/config-internal.php';
    echo \$c['database']['password'] ?? '';
  " 2>/dev/null || true)
  if [[ -n "${DB_NAME}" ]]; then
    mysql -u "${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" < "${SQL_FILE}" && echo "SQL schema product OK"
  fi
fi

echo ""
echo "Fatto. Aprire un prodotto con Ctrl+F5 nel browser."
