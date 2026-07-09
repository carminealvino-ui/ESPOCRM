#!/usr/bin/env bash
# PR provvigioni-regole-ariel — minusvalenze, bonus weekend, referenza personale.
# Richiede PR #99 (imponibile netto) già deployato.
#
# Uso:
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/provvigioni-regole-ariel-9999/tools/deploy-provvigioni-regole-ariel.sh?t=$(date +%s)" | bash
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="cursor/provvigioni-regole-ariel-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"
TS="$(date +%s)"

cd "${CRM_ROOT}" || exit 1

echo "=== Provvigioni: minus + bonus weekend + referenza personale ==="
echo "=== CRM: ${CRM_ROOT} ==="

fetch() {
  local path="$1"
  mkdir -p "$(dirname "$path")"
  curl -fL --retry 8 --retry-all-errors --retry-delay 2 --retry-max-time 120 \
    "${BASE}/${path}?t=${TS}" -o "${path}"
  echo "OK ${path}"
}

FILES=(
  custom/Espo/Custom/Services/ProvvigioneManager.php
  custom/Espo/Custom/Services/RegolaProvvigionaleCalculator.php
  custom/Espo/Custom/Resources/metadata/entityDefs/RegolaProvvigionale.json
  database/2026-05-26-gdl-ariel-2026-regole-provvigioni-seed.sql
  database/2026-07-06-bonus-weekend-regola-provvigioni-seed.sql
  database/2026-07-09-referenza-personale-regola-provvigioni-seed.sql
)

for rel in "${FILES[@]}"; do
  fetch "${rel}"
done

php clear_cache.php
php rebuild.php

echo ""
echo "=== Seed regole provvigionali ==="
DB_PASS="$(sed -n "s/.*'password' => '\([^']*\)'.*/\1/p" data/config-internal.php | head -1)"
DB_USER="$(sed -n "s/.*'user' => '\([^']*\)'.*/\1/p" data/config-internal.php | head -1)"
DB_NAME="$(sed -n "s/.*'dbname' => '\([^']*\)'.*/\1/p" data/config-internal.php | head -1)"
DB_HOST="$(sed -n "s/.*'host' => '\([^']*\)'.*/\1/p" data/config-internal.php | head -1)"
DB_NAME="${DB_NAME:-telcalli_espo}"
DB_HOST="${DB_HOST:-localhost}"

if [[ -z "${DB_USER}" || -z "${DB_PASS}" ]]; then
  echo "ERRORE: credenziali DB non lette da data/config-internal.php" >&2
  exit 1
fi

CNF="$(mktemp)"
chmod 600 "${CNF}"
trap 'rm -f "${CNF}"' EXIT
printf '[client]\nhost=%s\nuser=%s\npassword=%s\ndatabase=%s\n' \
  "${DB_HOST}" "${DB_USER}" "${DB_PASS}" "${DB_NAME}" > "${CNF}"

MYSQL_BIN="mariadb"
command -v mariadb >/dev/null 2>&1 || MYSQL_BIN="mysql"

for sql in \
  database/2026-05-26-gdl-ariel-2026-regole-provvigioni-seed.sql \
  database/2026-07-06-bonus-weekend-regola-provvigioni-seed.sql \
  database/2026-07-09-referenza-personale-regola-provvigioni-seed.sql
do
  "${MYSQL_BIN}" --defaults-extra-file="${CNF}" "${DB_NAME}" < "${sql}"
  echo "OK ${sql}"
done

php clear_cache.php

echo ""
echo "=== Ricalcolo provvigioni su tutti i contratti ==="
fetch tools/migrate-ricalcola-provvigioni-contratti.php
php tools/migrate-ricalcola-provvigioni-contratti.php

php clear_cache.php

echo ""
echo "=== Fatto ==="
echo "Atteso: contratti sab/dom → riga Bonus (Sabato-Domenica) 2%"
echo "Atteso: minus/plus negativo → riga Minus Provvigionale 35%"
echo "Atteso: appuntamento tipo Referenza Personale → regola Referenza Personale (6%) al posto base Ariel"
echo ""
echo "Solo anteprima ricalcolo:"
echo "  php tools/migrate-ricalcola-provvigioni-contratti.php --dry-run"
