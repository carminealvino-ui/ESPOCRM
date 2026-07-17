#!/usr/bin/env bash
# Hotfix: rimuove pannello "Provvigioni (calcolo)" e ripristina layout Contratto stabile
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-totale-provvigioni-sync-9999/tools/deploy-fix-quote-layout-hotfix.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/fix-totale-provvigioni-sync-9999"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

FILES=(
  "custom/Espo/Custom/Resources/layouts/Quote/detail.json"
  "custom/Espo/Custom/Resources/layouts/Quote/detailBottom.json"
  "custom/Espo/Custom/Resources/layouts/Quote/detailBottomTotal.json"
  "custom/Espo/Custom/Resources/layouts/Quote/finanziamento.json"
  "custom/Espo/Custom/Resources/layouts/Quote/relationships/provvigioni.json"
  "custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json"
)

echo "=== Hotfix layout Contratto (rimuove Provvigioni calcolo) ==="
for rel in "${FILES[@]}"; do
  mkdir -p "${CRM_ROOT}/$(dirname "${rel}")"
  curl -fsSL "${BASE}/${rel}?t=$(date +%s)" -o "${CRM_ROOT}/${rel}"
  echo "OK ${rel}"
done

cd "${CRM_ROOT}"

if grep -q 'Provvigioni (calcolo)' custom/Espo/Custom/Resources/layouts/Quote/detail.json; then
  echo "ERRORE: detail.json contiene ancora 'Provvigioni (calcolo)'"
  exit 1
fi

php clear_cache.php
php rebuild.php
php clear_cache.php

echo ""
echo "VERIFICA OK: pannello 'Provvigioni (calcolo)' rimosso"
echo "Ctrl+Shift+R nel browser"
