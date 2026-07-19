#!/usr/bin/env bash
# Bozza → Stato Contratto vuoto (nessuna selezione).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/COMMIT/tools/deploy-contratto-bozza-stato-null.sh" \
#     -o tools/deploy-contratto-bozza-stato-null.sh
#   bash tools/deploy-contratto-bozza-stato-null.sh

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
COMMIT="${DEPLOY_COMMIT:-03b535f1dfea9a1321f121d0038cf444dcdf68d9}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${COMMIT}"
FIX_TAG="contratto-bozza-stato-null"
STAMP=$(date +%Y%m%d-%H%M%S)

cd "${CRM_ROOT}"

echo "=== Deploy Bozza → Stato Contratto vuoto ==="
echo "COMMIT=${COMMIT}"

FILES=(
  "custom/Espo/Custom/Hooks/Quote/NormalizeDefaults.php"
  "custom/Espo/Custom/Hooks/Quote/ClearStatoContrattoWhenBozza.php"
  "custom/Espo/Custom/Hooks/Quote/SetPresentedWhenNumeroContratto.php"
  "custom/Espo/Custom/Actions/Opportunity/CreateContratto.php"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json"
  "tools/bonifica-quote-bozza-stato-null.php"
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

echo "=== Cache + rebuild ==="
rm -rf data/cache/* 2>/dev/null || true
php clear_cache.php || true
php rebuild.php

echo "=== Bonifica Contratto_00154 + tutti i Bozza ==="
php tools/bonifica-quote-bozza-stato-null.php Contratto_00154 || true
php tools/bonifica-quote-bozza-stato-null.php

echo ""
echo "=== Fine ==="
echo "Hard-refresh su Contratto_00154: Stato=Bozza, Stato Contratto=(vuoto)"
