#!/usr/bin/env bash
# Bonifica Opportunità con Appuntamento orfano (ID grezzo → vuoto o ricollegato).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/COMMIT/tools/deploy-fix-opportunity-appuntamento-orfano.sh" \
#     -o tools/deploy-fix-opportunity-appuntamento-orfano.sh
#   bash tools/deploy-fix-opportunity-appuntamento-orfano.sh

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
COMMIT="${DEPLOY_COMMIT:-6e05d547e0c15347245c1907bdcc6a8ad946c26c}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${COMMIT}"
FIX_TAG="fix-opportunity-appuntamento-orfano"
STAMP=$(date +%Y%m%d-%H%M%S)

cd "${CRM_ROOT}"

echo "=== Deploy fix Opportunità Appuntamento orfano ==="
echo "COMMIT=${COMMIT}"

FILES=(
  "custom/Espo/Custom/Services/OpportunityAppuntamentoOrphanRepair.php"
  "custom/Espo/Custom/Services/OpportunityFornitorePartnerRepair.php"
  "custom/Espo/Custom/Hooks/Opportunity/GlobalLogic.php"
  "tools/bonifica-opportunity-appuntamento-orfano.php"
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

if ! grep -q "VERSIONE: 2.2.10" custom/Espo/Custom/Hooks/Opportunity/GlobalLogic.php; then
  echo "ERRORE: GlobalLogic non è 2.2.10" >&2
  exit 1
fi

if ! grep -q "OpportunityAppuntamentoOrphanRepair" custom/Espo/Custom/Hooks/Opportunity/GlobalLogic.php; then
  echo "ERRORE: manca OpportunityAppuntamentoOrphanRepair in GlobalLogic" >&2
  exit 1
fi

echo "=== Cache ==="
rm -rf data/cache/* 2>/dev/null || true
php clear_cache.php || true
php rebuild.php

echo "=== 1) Dry-run Berana / ID orfano ==="
php tools/bonifica-opportunity-appuntamento-orfano.php --dry-run berana || true
php tools/bonifica-opportunity-appuntamento-orfano.php --dry-run 67ebb599b5324ca2c || true

echo ""
echo "=== 2) Applica Berana + ID orfano ==="
php tools/bonifica-opportunity-appuntamento-orfano.php berana || true
php tools/bonifica-opportunity-appuntamento-orfano.php 67ebb599b5324ca2c || true

echo ""
echo "=== 3) Bonifica completa orfani ==="
php tools/bonifica-opportunity-appuntamento-orfano.php

echo ""
echo "=== Fine ==="
echo "Atteso Berana: Appuntamento vuoto (o ricollegato se esiste un altro appuntamento sul Lead)"
echo "HookVersion Opportunità → 2.2.10"
