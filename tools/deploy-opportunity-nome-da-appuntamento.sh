#!/usr/bin/env bash
# Nome Opportunità: data + cliente (da Lead se manca) — fix "2025-08-09 - - PROGETTO".
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/COMMIT/tools/deploy-opportunity-nome-da-appuntamento.sh" \
#     -o tools/deploy-opportunity-nome-da-appuntamento.sh
#   bash tools/deploy-opportunity-nome-da-appuntamento.sh

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
COMMIT="${DEPLOY_COMMIT:-REPLACE_AFTER_COMMIT}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${COMMIT}"
FIX_TAG="opportunity-nome-da-appuntamento"
STAMP=$(date +%Y%m%d-%H%M%S)

cd "${CRM_ROOT}"

if [[ "${COMMIT}" == "REPLACE_AFTER_COMMIT" ]]; then
  echo "ERRORE: DEPLOY_COMMIT non pinato."
  exit 1
fi

echo "=== Deploy nome Opportunità v3 (fix doppio trattino / cliente da Lead) ==="
echo "COMMIT=${COMMIT}"

FILES=(
  "custom/Espo/Custom/Hooks/Opportunity/GlobalLogic.php"
  "custom/Espo/Custom/Services/OpportunityNameBuilder.php"
  "tools/bonifica-opportunity-nome-data.php"
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

echo "=== Download ==="
for rel in "${FILES[@]}"; do
  dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=${STAMP}" -o "${dest}"
  echo "OK ${rel}"
done

if ! grep -q "VERSIONE: 2.2.8" custom/Espo/Custom/Hooks/Opportunity/GlobalLogic.php; then
  echo "ERRORE: GlobalLogic non è 2.2.8" >&2
  exit 1
fi

echo "=== Cache ==="
rm -rf data/cache/* 2>/dev/null || true
php clear_cache.php || true
php rebuild.php

echo "=== 1) Aremi / doppio trattino ==="
php tools/bonifica-opportunity-nome-data.php --dry-run aremi || true
php tools/bonifica-opportunity-nome-data.php aremi || true
php tools/bonifica-opportunity-nome-data.php --dry-run "GAZEBO 800X400" || true
php tools/bonifica-opportunity-nome-data.php "GAZEBO 800X400" || true

echo "=== 2) Dry-run campione ==="
set +o pipefail
php tools/bonifica-opportunity-nome-data.php --dry-run 2>/dev/null | head -n 30 || true
set -o pipefail

echo ""
echo "=== 3) Applica bonifica completa (include nomi con ' - - ') ==="
php tools/bonifica-opportunity-nome-data.php

echo ""
echo "=== Fine ==="
echo "Atteso Aremi: 2025-08-09 - AREMI SALVATORE - PROGETTO - GAZEBO 800X400 - €. 15.250"
