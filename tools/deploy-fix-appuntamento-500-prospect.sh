#!/usr/bin/env bash
# Fix 500 Appuntamento: formula API pericolosa + prospect orfani.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/COMMIT/tools/deploy-fix-appuntamento-500-prospect.sh" \
#     -o tools/deploy-fix-appuntamento-500-prospect.sh
#   bash tools/deploy-fix-appuntamento-500-prospect.sh

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
COMMIT="${DEPLOY_COMMIT:-68f6e778e06eb6d6430c48dab2e8446400c038cb}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${COMMIT}"
FIX_TAG="fix-appuntamento-500-prospect"
STAMP=$(date +%Y%m%d-%H%M%S)

cd "${CRM_ROOT}"


echo "=== Deploy fix Appuntamento 500 (formula + prospect orfano) ==="
echo "COMMIT=${COMMIT}"

FILES=(
  "custom/Espo/Custom/Resources/metadata/formula/Appuntamento.json"
  "custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php"
  "tools/diagnose-appuntamento-500.php"
  "tools/bonifica-appuntamento-prospect-orfano.php"
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

if ! grep -q '1.7.15' custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php; then
  echo "ERRORE: GlobalLogic Appuntamento non è 1.7.15" >&2
  exit 1
fi

if grep -q 'string\\\\concatenate(indirizzoPostalCode' custom/Espo/Custom/Resources/metadata/formula/Appuntamento.json; then
  echo "ERRORE: beforeSaveApiScript pericoloso ancora presente" >&2
  exit 1
fi

echo "=== Cache ==="
rm -rf data/cache/* 2>/dev/null || true
php clear_cache.php || true
php rebuild.php

echo "=== Diagnosi record 6900a442ee92bd824 ==="
php tools/diagnose-appuntamento-500.php 6900a442ee92bd824 || true

echo "=== Bonifica prospect orfani (record + massiva) ==="
php tools/bonifica-appuntamento-prospect-orfano.php --dry-run 6900a442ee92bd824 || true
php tools/bonifica-appuntamento-prospect-orfano.php 6900a442ee92bd824 || true
php tools/bonifica-appuntamento-prospect-orfano.php --dry-run | head -n 40 || true
set +o pipefail
php tools/bonifica-appuntamento-prospect-orfano.php
set -o pipefail

echo ""
echo "=== Fine ==="
echo "Riapri #Appuntamento/view/6900a442ee92bd824 (hard-refresh)"
