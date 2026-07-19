#!/usr/bin/env bash
# Nome Opportunità con data da Appuntamento (fix "- LOMMI MAURIZIO - ...").
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

echo "=== Deploy nome Opportunità da data Appuntamento (v2) ==="
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

if ! grep -q "VERSIONE: 2.2.7" custom/Espo/Custom/Hooks/Opportunity/GlobalLogic.php; then
  echo "ERRORE: GlobalLogic non è 2.2.7" >&2
  exit 1
fi

echo "=== Cache ==="
rm -rf data/cache/* 2>/dev/null || true
php clear_cache.php || true
php rebuild.php

echo "=== 1) Lommi (id + testo) ==="
php tools/bonifica-opportunity-nome-data.php --dry-run 681d9c063557522f6 || true
php tools/bonifica-opportunity-nome-data.php 681d9c063557522f6 || true
php tools/bonifica-opportunity-nome-data.php --dry-run lommi || true
php tools/bonifica-opportunity-nome-data.php lommi || true

echo "=== 2) Dry-run campione (max 30, senza interrompere lo script) ==="
set +o pipefail
php tools/bonifica-opportunity-nome-data.php --dry-run 2>/dev/null | head -n 30 || true
set -o pipefail

echo ""
echo "=== 3) Applica bonifica completa ==="
php tools/bonifica-opportunity-nome-data.php

echo ""
echo "=== Fine ==="
echo "Atteso Lommi: 2025-04-08 - LOMMI MAURIZIO - ARTEL - CLIMA 9000BTU - €. 3.000"
