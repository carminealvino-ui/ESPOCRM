#!/usr/bin/env bash
# Attiva entità Regole Provvigionali (RegolaProvvigionale) + tabella + seed.
# Risolve 404 su #RegolaProvvigionale/view/...
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/COMMIT/tools/deploy-regola-provvigionale-404.sh" \
#     -o tools/deploy-regola-provvigionale-404.sh
#   bash tools/deploy-regola-provvigionale-404.sh

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
COMMIT="${DEPLOY_COMMIT:-REPLACE_COMMIT}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${COMMIT}"
FIX_TAG="regola-provvigionale-404"
STAMP=$(date +%Y%m%d-%H%M%S)

cd "${CRM_ROOT}"

echo "=== Deploy RegolaProvvigionale (fix 404) ==="
echo "COMMIT=${COMMIT}"

FILES=(
  "custom/Espo/Custom/Entities/RegolaProvvigionale.php"
  "custom/Espo/Custom/Repositories/RegolaProvvigionale.php"
  "custom/Espo/Custom/Resources/metadata/entityDefs/RegolaProvvigionale.json"
  "custom/Espo/Custom/Resources/metadata/scopes/RegolaProvvigionale.json"
  "custom/Espo/Custom/Resources/metadata/clientDefs/RegolaProvvigionale.json"
  "custom/Espo/Custom/Resources/metadata/aclDefs/RegolaProvvigionale.json"
  "custom/Espo/Custom/Resources/metadata/recordDefs/RegolaProvvigionale.json"
  "custom/Espo/Custom/Resources/metadata/logicDefs/RegolaProvvigionale.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/FornitorePartner.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/ProductBrand.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/ProductCategory.json"
  "custom/Espo/Custom/Resources/layouts/RegolaProvvigionale/detail.json"
  "custom/Espo/Custom/Resources/layouts/RegolaProvvigionale/detailSmall.json"
  "custom/Espo/Custom/Resources/layouts/RegolaProvvigionale/edit.json"
  "custom/Espo/Custom/Resources/layouts/RegolaProvvigionale/editSmall.json"
  "custom/Espo/Custom/Resources/layouts/RegolaProvvigionale/list.json"
  "custom/Espo/Custom/Resources/layouts/RegolaProvvigionale/listSmall.json"
  "custom/Espo/Custom/Resources/layouts/RegolaProvvigionale/filters.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/RegolaProvvigionale.json"
  "database/2026-07-03-regola-provvigionale-create-table.sql"
  "tools/create-regola-provvigionale-table.php"
  "tools/seed-regole-provvigioni.php"
)

mkdir -p tools/backup-manifests
printf '%s\n' "${FILES[@]}" > "tools/backup-manifests/${FIX_TAG}.files"

has_backup() {
  local sessions="${CRM_ROOT}/backup_dev/_sessions"
  [[ -d "${sessions}" ]] || return 1
  local latest
  latest="$(find "${sessions}" -maxdepth 1 -type d -name "*_${FIX_TAG}" 2>/dev/null | sort -r | head -1)"
  [[ -n "${latest}" && -f "${latest}/manifest.txt" && -f "${latest}/files.list" ]]
}

if [[ "${SKIP_BACKUP_CHECK:-}" != "1" ]] && ! has_backup; then
  if [[ -f tools/backup-dev-batch.sh ]]; then
    bash tools/backup-dev-batch.sh "${FIX_TAG}" --manifest "tools/backup-manifests/${FIX_TAG}.files" || true
  fi
fi

LOCAL_BACKUP="${CRM_ROOT}/backup/${FIX_TAG}/server-${STAMP}"
mkdir -p "${LOCAL_BACKUP}"

for rel in "${FILES[@]}"; do
  src="${CRM_ROOT}/${rel}"
  if [[ -f "${src}" ]]; then
    mkdir -p "${LOCAL_BACKUP}/$(dirname "${rel}")"
    cp -a "${src}" "${LOCAL_BACKUP}/${rel}"
    echo "BACKUP ${rel}"
  else
    echo "SKIP backup (nuovo) ${rel}"
  fi
done

echo "=== Download da commit ${COMMIT} ==="
for rel in "${FILES[@]}"; do
  dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=${STAMP}" -o "${dest}"
  echo "OK ${rel}"
done

echo "=== Crea tabella regola_provvigionale ==="
php tools/create-regola-provvigionale-table.php

echo "=== Cache + rebuild ==="
php clear_cache.php
php rebuild.php

echo "=== Seed regole (include arielBase105) ==="
php tools/seed-regole-provvigioni.php --only=ariel || php tools/seed-regole-provvigioni.php

echo "=== Verifica arielBase105 ==="
php -r '
require "bootstrap.php";
$app = new Espo\Core\Application();
$app->setupSystemUser();
$em = $app->getContainer()->get("entityManager");
$e = $em->getEntityById("RegolaProvvigionale", "arielBase105");
echo $e ? ("OK arielBase105 = ".$e->get("name")."\n") : "ERRORE: arielBase105 assente\n";
'

echo ""
echo "=== Fine ==="
echo "Apri: #RegolaProvvigionale oppure #RegolaProvvigionale/view/arielBase105"
echo "Menu: Regole Provvigionali"
