#!/usr/bin/env bash
# Applica seed regole provvigionali su DB EspoCRM (produzione).
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
SQL_FILE="$REPO_ROOT/database/2026-05-28-regole-provvigionali-complete-seed.sql"

if [[ ! -f "$SQL_FILE" ]]; then
  echo "File SQL non trovato: $SQL_FILE" >&2
  exit 1
fi

CRM_DIR="${CRM_DIR:-$HOME/public_html/crm/mec-group}"

if [[ ! -f "$CRM_DIR/data/config-internal.php" ]]; then
  echo "Impostare CRM_DIR o eseguire da cartella CRM con config-internal.php" >&2
  exit 1
fi

DB_NAME=$(php -r "
\$c = include '$CRM_DIR/data/config-internal.php';
echo \$c['database']['dbname'] ?? '';
")
DB_USER=$(php -r "
\$c = include '$CRM_DIR/data/config-internal.php';
echo \$c['database']['user'] ?? '';
")
DB_PASS=$(php -r "
\$c = include '$CRM_DIR/data/config-internal.php';
echo \$c['database']['password'] ?? '';
")
DB_HOST=$(php -r "
\$c = include '$CRM_DIR/data/config-internal.php';
echo \$c['database']['host'] ?? 'localhost';
")

echo "Applico seed regole provvigionali su $DB_NAME ..."
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$SQL_FILE"
echo "Fatto. Eseguire: cd $CRM_DIR && php command.php rebuild && rm -rf data/cache/*"
