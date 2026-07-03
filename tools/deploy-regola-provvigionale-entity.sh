#!/usr/bin/env bash
# Entità RegolaProvvigionale (Regole provvigioni): metadata, layout, relazioni.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/regola-provvigionale-entity-9999/tools/deploy-regola-provvigionale-entity.sh?t=$(date +%s)" | bash
#   php clear_cache.php && php rebuild.php
#   mysql ... < database/2026-07-03-regola-provvigionale-seed-minimal.sql   # opzionale se tabella vuota

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
echo "=== Cache ==="
cd "${CRM_ROOT}"
php clear_cache.php
php rebuild.php

echo ""
echo "=== Fine ==="
echo "Menu: Amministrazione → Entità → Regole provvigioni (o cerca nel tab)"
echo "Seed ARQUATI/Ariel (se non già eseguiti):"
echo "  mysql ... < database/2026-05-26-arquati-pnc-regole-provvigioni-seed.sql"
echo "  mysql ... < database/2026-05-26-gdl-ariel-2026-regole-provvigioni-seed.sql"
