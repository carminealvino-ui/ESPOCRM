#!/usr/bin/env bash
# Collega regole provvigionali a Provvigione (subpanel contratto) + pulsante ricalcolo.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/provvigione-regole-contratto-9999/tools/deploy-provvigione-regole-contratto.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/provvigione-regole-contratto-9999"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)
LOCAL_BACKUP="${CRM_ROOT}/backup/provvigione-regole-contratto/server-${STAMP}"

echo "=== Backup in ${LOCAL_BACKUP} ==="
mkdir -p "${LOCAL_BACKUP}"

backup_if_exists() {
  local rel="$1"
  local src="${CRM_ROOT}/${rel}"
  if [[ -f "${src}" ]]; then
    mkdir -p "${LOCAL_BACKUP}/$(dirname "${rel}")"
    cp -a "${src}" "${LOCAL_BACKUP}/${rel}"
    echo "BACKUP ${rel}"
  fi
}

FILES=(
  "custom/Espo/Custom/Services/ProvvigioneManager.php"
  "custom/Espo/Custom/Services/RegolaProvvigionaleCalculator.php"
  "custom/Espo/Custom/Hooks/Provvigione/AccrualAndAmount.php"
  "custom/Espo/Custom/Hooks/Provvigione/BeforeSaveLegacy.php"
  "custom/Espo/Custom/Hooks/Provvigione/AfterSaveContratto.php"
  "custom/Espo/Custom/Hooks/Quote/BeforeSave.php"
  "custom/Espo/Custom/Actions/Quote/RicalcolaProvvigioni.php"
  "custom/Espo/Custom/Controllers/Quote.php"
  "custom/Espo/Custom/Resources/metadata/app/actions.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Provvigione.json"
  "custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json"
  "custom/Espo/Custom/Resources/metadata/clientDefs/Provvigione.json"
  "custom/Espo/Custom/Resources/layouts/Quote/detailBottom.json"
  "custom/Espo/Custom/Resources/layouts/Provvigione/detail.json"
  "custom/Espo/Custom/Resources/layouts/Provvigione/list.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Provvigione.json"
  "client/custom/src/views/provvigione/record/detail.js"
  "client/custom/src/views/provvigione/record/edit.js"
  "client/custom/src/handlers/quote/ricalcola-provvigioni.js"
)

LEGACY_REMOVE=(
  "client/custom/src/views/quote/record/detail.js"
)

for rel in "${FILES[@]}"; do
  backup_if_exists "${rel}"
done

for rel in "${LEGACY_REMOVE[@]}"; do
  src="${CRM_ROOT}/${rel}"
  if [[ -f "${src}" ]]; then
    backup_if_exists "${rel}"
    rm -f "${src}"
    echo "REMOVED legacy ${rel}"
  fi
done

echo "=== Deploy da ${BRANCH} ==="
for rel in "${FILES[@]}"; do
  mkdir -p "${CRM_ROOT}/$(dirname "${rel}")"
  curl -fsSL "${BASE}/${rel}?t=$(date +%s)" -o "${CRM_ROOT}/${rel}"
  echo "OK ${rel}"
done

echo ""
echo "=== Cache + rebuild ==="
cd "${CRM_ROOT}"
php clear_cache.php
php rebuild.php

echo ""
echo "=== Fine ==="
echo "Contratto (Quote): subpanel Provvigioni + pulsante Ricalcola provvigioni"
echo "Salvataggio manuale provvigione: calcolo da regole provvigionali"
