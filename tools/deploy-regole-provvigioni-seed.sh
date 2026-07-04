#!/usr/bin/env bash
# Popola regole provvigionali ARQUATI PNC + Ariel 2026.
#
# Prerequisito: entità RegolaProvvigionale e tabella DB (deploy-regola-provvigionale-entity.sh).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/regola-provvigionale-entity-9999/tools/deploy-regole-provvigioni-seed.sh?t=$(date +%s)" | bash
#
# Opzionale: ONLY=arquati|ariel|all (default all)
# Anteprima: DRY_RUN=1 curl ... | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/regola-provvigionale-entity-9999"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
ONLY="${ONLY:-all}"
DRY_RUN="${DRY_RUN:-0}"

echo "=== Deploy seed regole provvigioni (${BRANCH}) ==="

FILES=(
  "tools/seed-regole-provvigioni.php"
  "database/2026-07-04-regole-provvigioni-seed-completo.sql"
)

for rel in "${FILES[@]}"; do
  mkdir -p "${CRM_ROOT}/$(dirname "${rel}")"
  curl -fsSL "${BASE}/${rel}?t=$(date +%s)" -o "${CRM_ROOT}/${rel}"
  echo "OK ${rel}"
done

echo ""
echo "=== Verifica tabella regola_provvigionale ==="
cd "${CRM_ROOT}"

if [[ -f tools/create-regola-provvigionale-table.php ]]; then
  php tools/create-regola-provvigionale-table.php
else
  echo "ATTENZIONE: create-regola-provvigionale-table.php assente — verificare tabella manualmente"
fi

echo ""
echo "=== Seed regole (--only=${ONLY}) ==="

SEED_CMD=(php tools/seed-regole-provvigioni.php "--only=${ONLY}")

if [[ "${DRY_RUN}" == "1" ]]; then
  SEED_CMD+=(--dry-run)
fi

"${SEED_CMD[@]}"

echo ""
echo "=== Cache ==="
php clear_cache.php

echo ""
echo "=== Fine ==="
echo "Regole visibili in CRM: Regole provvigioni"
echo "Totale atteso: 25 (22 ARQUATI + 3 Ariel)"
