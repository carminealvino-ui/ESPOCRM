#!/usr/bin/env bash
# Bonifica massiva provvigioni contratti (dopo deploy fix legacy Ariel).
#
# Uso:
#   SHA=68e14063 ./tools/deploy-bonifica-provvigioni-tutti.sh --dry-run
#   SHA=68e14063 ./tools/deploy-bonifica-provvigioni-tutti.sh
#   SHA=68e14063 ./tools/deploy-bonifica-provvigioni-tutti.sh --force --verbose
#
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
SHA="${SHA:-68e14063}"
REPO_RAW="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${SHA}"

echo "=== Deploy fix provvigioni (SHA ${SHA}) ==="

FILES=(
  "custom/Espo/Custom/Services/ProvvigioneManager.php"
  "custom/Espo/Custom/Services/ProvvigioneAccrual.php"
  "custom/Espo/Custom/Services/QuotePricingCalculator.php"
  "custom/Espo/Custom/Services/RegolaProvvigionaleCalculator.php"
  "database/2026-07-07-ariel-legacy-scalette-minus-seed.sql"
  "tools/migrate-ricalcola-provvigioni-contratti.php"
  "tools/run-regola-provvigionale-seed.php"
  "tools/seed-regole-provvigioni-ariel-legacy.php"
)

for rel in "${FILES[@]}"; do
  dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "$dest")"
  echo "  -> ${rel}"
  curl -fsSL "${REPO_RAW}/${rel}" -o "$dest"
done

cd "$CRM_ROOT"
php clear_cache.php

echo ""
echo "=== Anteprima bonifica (dry-run) ==="
php tools/migrate-ricalcola-provvigioni-contratti.php --dry-run --verbose "$@"

echo ""
read -r -p "Eseguire bonifica su TUTTI i contratti? [y/N] " confirm

if [[ ! "$confirm" =~ ^[Yy]$ ]]; then
  echo "Annullato."
  exit 0
fi

echo ""
echo "=== Bonifica in corso ==="
php tools/migrate-ricalcola-provvigioni-contratti.php --force --verbose "$@"

echo ""
echo "Bonifica completata."
