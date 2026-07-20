#!/usr/bin/env bash
# Verifica/ripristina Appuntamento soft-deleted + bonifica orfani Opportunity.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/COMMIT/tools/deploy-fix-opportunity-appuntamento-orfano.sh" \
#     -o tools/deploy-fix-opportunity-appuntamento-orfano.sh
#   bash tools/deploy-fix-opportunity-appuntamento-orfano.sh

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
COMMIT="${DEPLOY_COMMIT:-0934f30a9091ae42f602998fafd53b4de736f197}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${COMMIT}"
FIX_TAG="fix-opportunity-appuntamento-orfano"
STAMP=$(date +%Y%m%d-%H%M%S)

cd "${CRM_ROOT}"

echo "=== Deploy ripristino Appuntamento soft-deleted + orfani ==="
echo "COMMIT=${COMMIT}"

FILES=(
  "custom/Espo/Custom/Services/OpportunityAppuntamentoOrphanRepair.php"
  "custom/Espo/Custom/Services/OpportunityFornitorePartnerRepair.php"
  "custom/Espo/Custom/Hooks/Opportunity/GlobalLogic.php"
  "tools/verifica-ripristina-appuntamento.php"
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

if ! grep -q "VERSIONE: 2.2.11" custom/Espo/Custom/Hooks/Opportunity/GlobalLogic.php; then
  echo "ERRORE: GlobalLogic non è 2.2.11" >&2
  exit 1
fi

if ! grep -q "soft-deleted" custom/Espo/Custom/Services/OpportunityAppuntamentoOrphanRepair.php; then
  echo "ERRORE: manca logica soft-deleted in OpportunityAppuntamentoOrphanRepair" >&2
  exit 1
fi

echo "=== Cache ==="
rm -rf data/cache/* 2>/dev/null || true
php clear_cache.php || true
php rebuild.php

echo ""
echo "=== 1) VERIFICA DB (soft-deleted?) — Berana / ID ==="
php tools/verifica-ripristina-appuntamento.php --dry-run 67ebb599b5324ca2c || true
php tools/verifica-ripristina-appuntamento.php --dry-run berana || true

echo ""
echo "=== 2) RIPRISTINA se deleted=1 ==="
php tools/verifica-ripristina-appuntamento.php 67ebb599b5324ca2c || true
php tools/verifica-ripristina-appuntamento.php berana || true

echo ""
echo "=== 3) Scan altri orfani soft-deleted ==="
php tools/verifica-ripristina-appuntamento.php --scan-orphans --dry-run || true
php tools/verifica-ripristina-appuntamento.php --scan-orphans || true

echo ""
echo "=== 4) Bonifica residui (solo se record davvero assente) ==="
php tools/bonifica-opportunity-appuntamento-orfano.php --dry-run berana || true
php tools/bonifica-opportunity-appuntamento-orfano.php berana || true

echo ""
echo "=== Fine ==="
echo "Se status=soft-deleted → ripristinato (Appuntamento di nuovo visibile)."
echo "Se status=missing → serve backup DB, non recuperabile da qui."
echo "HookVersion Opportunità → 2.2.11"
