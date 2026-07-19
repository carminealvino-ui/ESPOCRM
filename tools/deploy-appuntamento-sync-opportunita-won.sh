#!/usr/bin/env bash
# Allinea Appuntamenti a Opportunità vinte/installate (es. Lommi Maurizio).
# v2: non sovrascrive esiti esistenti; skipHooks anti-hang Google; ripristino esiti.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/COMMIT/tools/deploy-appuntamento-sync-opportunita-won.sh" \
#     -o tools/deploy-appuntamento-sync-opportunita-won.sh
#   bash tools/deploy-appuntamento-sync-opportunita-won.sh

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
COMMIT="${DEPLOY_COMMIT:-REPLACE_AFTER_COMMIT}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${COMMIT}"
FIX_TAG="appuntamento-sync-opportunita-won"
STAMP=$(date +%Y%m%d-%H%M%S)

cd "${CRM_ROOT}"

if [[ "${COMMIT}" == "REPLACE_AFTER_COMMIT" ]]; then
  echo "ERRORE: DEPLOY_COMMIT non pinato."
  exit 1
fi

echo "=== Deploy sync Appuntamento ← Opportunità vinta (v2 safe) ==="
echo "COMMIT=${COMMIT}"

FILES=(
  "custom/Espo/Custom/Services/OpportunityAppuntamentoOutcomeSync.php"
  "custom/Espo/Custom/Hooks/Opportunity/SyncAppuntamentoFromWon.php"
  "tools/bonifica-appuntamento-da-opportunita-won.php"
  "tools/ripristina-esito-appuntamento-dopo-bonifica-won.php"
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

echo "=== Cache ==="
rm -rf data/cache/* 2>/dev/null || true
php clear_cache.php || true
php rebuild.php

echo "=== 1) Ripristina esiti storici sovrascritti per errore ==="
php tools/ripristina-esito-appuntamento-dopo-bonifica-won.php --dry-run || true
php tools/ripristina-esito-appuntamento-dopo-bonifica-won.php || true

echo "=== 2) Dry-run bonifica (solo Non allineati: non Held/Chiuso Positivamente) ==="
php tools/bonifica-appuntamento-da-opportunita-won.php --dry-run | head -80

echo ""
echo "=== 3) Applica bonifica ==="
php tools/bonifica-appuntamento-da-opportunita-won.php

echo ""
echo "=== Verifica Lommi ==="
php tools/bonifica-appuntamento-da-opportunita-won.php --dry-run lommi || true

echo ""
echo "=== Fine ==="
echo "Atteso Lommi: Svolto / Chiuso Positivamente (esito Venduto Cartaceo se era vuoto)"
