#!/usr/bin/env bash
# Entità RegolaProvvigionale (Regole provvigioni): metadata, tabella DB, layout.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/regola-provvigionale-entity-9999/tools/deploy-regola-provvigionale-entity.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/regola-provvigionale-entity-9999"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)
LOCAL_BACKUP="${CRM_ROOT}/backup/regola-provvigionale-entity/server-${STAMP}"

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
  "custom/Espo/Custom/Entities/RegolaProvvigionale.php"
  "custom/Espo/Custom/Repositories/RegolaProvvigionale.php"
  "custom/Espo/Custom/Resources/metadata/entityDefs/RegolaProvvigionale.json"
  "custom/Espo/Custom/Resources/metadata/scopes/RegolaProvvigionale.json"
  "custom/Espo/Custom/Resources/metadata/clientDefs/RegolaProvvigionale.json"
  "custom/Espo/Custom/Resources/metadata/logicDefs/RegolaProvvigionale.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/FornitorePartner.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/ProductBrand.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/ProductCategory.json"
  "custom/Espo/Custom/Resources/layouts/RegolaProvvigionale/detail.json"
  "custom/Espo/Custom/Resources/layouts/RegolaProvvigionale/list.json"
  "custom/Espo/Custom/Resources/layouts/RegolaProvvigionale/filters.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/RegolaProvvigionale.json"
  "database/2026-07-03-regola-provvigionale-create-table.sql"
  "tools/create-regola-provvigionale-table.php"
)

for rel in "${FILES[@]}"; do
  backup_if_exists "${rel}"
done

echo "=== Deploy da ${BRANCH} ==="
for rel in "${FILES[@]}"; do
  mkdir -p "${CRM_ROOT}/$(dirname "${rel}")"
  curl -fsSL "${BASE}/${rel}?t=$(date +%s)" -o "${CRM_ROOT}/${rel}"
  echo "OK ${rel}"
done

echo ""
echo "=== Crea tabella regola_provvigionale (se assente) ==="
cd "${CRM_ROOT}"
php tools/create-regola-provvigionale-table.php

echo ""
echo "=== Cache + rebuild ==="
php clear_cache.php
php rebuild.php

echo ""
echo "=== Verifica tabella ==="
php tools/create-regola-provvigionale-table.php

echo ""
echo "=== Fine ==="
echo "Menu CRM: Regole provvigioni"
echo ""
echo "Popolare regole (opzionale, da mysql client o phpMyAdmin):"
echo "  database/2026-05-26-arquati-pnc-regole-provvigioni-seed.sql"
echo "  database/2026-05-26-gdl-ariel-2026-regole-provvigioni-seed.sql"
