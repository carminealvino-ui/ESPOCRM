#!/usr/bin/env bash
# Bonifica Fornitore/Partner su Opportunità (ID grezzo → nome GFB).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/COMMIT/tools/deploy-bonifica-fornitore-partner-gfb.sh" \
#     -o tools/deploy-bonifica-fornitore-partner-gfb.sh
#   bash tools/deploy-bonifica-fornitore-partner-gfb.sh

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
COMMIT="${DEPLOY_COMMIT:-be1b1bf6bd64f6726377849b4186c70bd7e4b84c}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${COMMIT}"
FIX_TAG="bonifica-fornitore-partner-gfb"
STAMP=$(date +%Y%m%d-%H%M%S)

cd "${CRM_ROOT}"

echo "=== Deploy bonifica Fornitore/Partner GFB ==="
echo "COMMIT=${COMMIT}"

FILES=(
  "custom/Espo/Custom/Services/OpportunityFornitorePartnerRepair.php"
  "custom/Espo/Custom/Hooks/Opportunity/GlobalLogic.php"
  "tools/bonifica-opportunity-fornitore-partner.php"
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

if ! grep -q "VERSIONE: 2.2.9" custom/Espo/Custom/Hooks/Opportunity/GlobalLogic.php; then
  echo "ERRORE: GlobalLogic non è 2.2.9" >&2
  exit 1
fi

if ! grep -q "OpportunityFornitorePartnerRepair" custom/Espo/Custom/Hooks/Opportunity/GlobalLogic.php; then
  echo "ERRORE: manca OpportunityFornitorePartnerRepair in GlobalLogic" >&2
  exit 1
fi

echo "=== Cache ==="
rm -rf data/cache/* 2>/dev/null || true
php clear_cache.php || true
php rebuild.php

echo "=== 1) Dry-run GFB / ID orfano ==="
php tools/bonifica-opportunity-fornitore-partner.php --dry-run gfb || true
php tools/bonifica-opportunity-fornitore-partner.php --dry-run 690f48d25850e9719 || true

echo ""
echo "=== 2) Applica GFB + ID orfano noto ==="
php tools/bonifica-opportunity-fornitore-partner.php gfb || true
php tools/bonifica-opportunity-fornitore-partner.php 690f48d25850e9719 || true

echo ""
echo "=== 3) Bonifica completa (tutti gli orfani risolvibili) ==="
php tools/bonifica-opportunity-fornitore-partner.php

echo ""
echo "=== Fine ==="
echo "Atteso in lista: Fornitore/Partner = GFB (non più 690f48d25850e9719)"
echo "HookVersion Opportunità → 2.2.9"
