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
DB_HOST=$(php -r '$c=include "data/config.php"; echo $c["database"]["host"] ?? "localhost";')
DB_NAME=$(php -r '$c=include "data/config.php"; echo $c["database"]["dbname"];')
DB_USER=$(php -r '$c=include "data/config.php"; echo $c["database"]["user"];')
DB_PASS=$(php -r '$c=include "data/config.php"; echo $c["database"]["password"];')

for sql in \
  database/2026-05-26-gdl-ariel-2026-regole-provvigioni-seed.sql \
  database/2026-07-06-bonus-weekend-regola-provvigioni-seed.sql \
  database/2026-07-09-referenza-personale-regola-provvigioni-seed.sql
do
  mysql -h "${DB_HOST}" -u "${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" < "${sql}"
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
