#!/usr/bin/env bash
# Call esito Non interessato → opportunità Closed Lost + lead Perso.
# L'Appuntamento Pending non viene modificato (resta storicizzato).
#
# Uso:
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/f01b6b4a32929bf4e85f496d7f5dad7f1fe37b1a/tools/deploy-call-esito-non-interessato-opp.sh" \
#     -o tools/deploy-call-esito-non-interessato-opp.sh
#   bash tools/deploy-call-esito-non-interessato-opp.sh
#
# Poi:
#   php clear_cache.php && php rebuild.php
#   php tools/diagnose-call-esito-opportunity.php SEDDA
#   php tools/diagnose-call-esito-opportunity.php SEDDA --fix

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
# Pin al commit (evita cache CDN su nome branch).
COMMIT="${DEPLOY_COMMIT:-f01b6b4a32929bf4e85f496d7f5dad7f1fe37b1a}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${COMMIT}"
FIX_TAG="call-esito-non-interessato-opp"
MANIFEST_REL="tools/backup-manifests/call-esito-non-interessato-opp.files"
SCRIPT_VERSION="2026-07-19c-bootstrap"
STAMP=$(date +%Y%m%d-%H%M%S)

cd "${CRM_ROOT}"

echo "=== Deploy Call esito Non interessato → Opp/Lead ==="
echo "CRM_ROOT=${CRM_ROOT}"
echo "COMMIT=${COMMIT}"
echo "SCRIPT_VERSION=${SCRIPT_VERSION}"

FILES=(
  "custom/Espo/Custom/Services/CallEsitoOpportunitySync.php"
  "custom/Espo/Custom/Hooks/Call/SyncOpportunityFromEsito.php"
  "custom/Espo/Custom/Hooks/Call/SyncLeadFromEsito.php"
  "tools/bonifica-call-esito-opportunity-persa.php"
  "tools/diagnose-call-esito-opportunity.php"
  "${MANIFEST_REL}"
)

download_file() {
  local rel="$1"
  local dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=${STAMP}" -o "${dest}"
  echo "OK download ${rel}"
}

has_backup() {
  local sessions="${CRM_ROOT}/backup_dev/_sessions"
  [[ -d "${sessions}" ]] || return 1
  local latest
  latest="$(find "${sessions}" -maxdepth 1 -type d -name "*_${FIX_TAG}" 2>/dev/null | sort -r | head -1)"
  [[ -n "${latest}" && -f "${latest}/manifest.txt" && -f "${latest}/files.list" ]]
}

echo "=== Bootstrap manifest ==="
download_file "${MANIFEST_REL}"

if [[ "${SKIP_BACKUP_CHECK:-}" != "1" ]] && ! has_backup; then
  if [[ ! -f "${CRM_ROOT}/tools/backup-dev-batch.sh" ]]; then
    echo "ERRORE: manca tools/backup-dev-batch.sh" >&2
    exit 1
  fi

  echo "=== PASSO 0 automatico: backup_dev ==="
  bash "${CRM_ROOT}/tools/backup-dev-batch.sh" "${FIX_TAG}" \
    --manifest "${CRM_ROOT}/${MANIFEST_REL}"
fi

if has_backup; then
  latest="$(find "${CRM_ROOT}/backup_dev/_sessions" -maxdepth 1 -type d -name "*_${FIX_TAG}" 2>/dev/null | sort -r | head -1)"
  echo "Backup rilevato: ${latest#${CRM_ROOT}/}"
else
  echo "ERRORE: backup sessione non creato." >&2
  exit 1
fi

LOCAL_BACKUP="${CRM_ROOT}/backup/call-esito-non-interessato-opp/server-${STAMP}"
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

echo "=== Download codice ==="
for rel in "${FILES[@]}"; do
  download_file "${rel}"
done

echo ""
echo "=== Deploy completato (version ${SCRIPT_VERSION}) ==="
echo "Poi:"
echo "  php clear_cache.php && php rebuild.php"
echo "  php tools/diagnose-call-esito-opportunity.php SEDDA"
echo "  php tools/diagnose-call-esito-opportunity.php SEDDA --fix"
