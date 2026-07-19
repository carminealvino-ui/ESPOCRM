#!/usr/bin/env bash
# Call esito Non interessato → opportunità Closed Lost + lead Perso.
# L'Appuntamento Pending non viene modificato (resta storicizzato).
#
# Uso (un solo blocco — scarica manifest, backup, deploy):
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/call-esito-non-interessato-opp-9999/tools/deploy-call-esito-non-interessato-opp.sh" \
#     -o tools/deploy-call-esito-non-interessato-opp.sh
#   bash tools/deploy-call-esito-non-interessato-opp.sh
#
# Poi:
#   php clear_cache.php && php rebuild.php
#   php tools/diagnose-call-esito-opportunity.php SEDDA
#   php tools/diagnose-call-esito-opportunity.php SEDDA --fix

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/call-esito-non-interessato-opp-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
FIX_TAG="call-esito-non-interessato-opp"
MANIFEST_REL="tools/backup-manifests/call-esito-non-interessato-opp.files"
SCRIPT_PATH="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/$(basename "${BASH_SOURCE[0]}")"
STAMP=$(date +%Y%m%d-%H%M%S)

cd "${CRM_ROOT}"

if [[ "${DEPLOY_SELF_UPDATED:-}" != "1" ]]; then
  tmp_script="${SCRIPT_PATH}.new.$$"
  if curl -fsSL -o "${tmp_script}" "${BASE}/tools/deploy-call-esito-non-interessato-opp.sh?t=$(date +%s)"; then
    if ! cmp -s "${SCRIPT_PATH}" "${tmp_script}"; then
      mv "${tmp_script}" "${SCRIPT_PATH}"
      chmod +x "${SCRIPT_PATH}"
      echo "Script deploy aggiornato da ${BRANCH}, riesecuzione..."
      exec env DEPLOY_SELF_UPDATED=1 bash "${SCRIPT_PATH}" "$@"
    fi
    rm -f "${tmp_script}"
  else
    rm -f "${tmp_script}"
    echo "ATTENZIONE: impossibile aggiornare lo script deploy da GitHub, uso copia locale." >&2
  fi
fi

echo "=== Deploy Call esito Non interessato → Opp/Lead → ${CRM_ROOT} ==="

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

# Bootstrap: scarica sempre il manifest prima del backup (file nuovi non esistono ancora sul server).
echo "=== Bootstrap manifest da ${BRANCH} ==="
download_file "${MANIFEST_REL}"

if [[ "${SKIP_BACKUP_CHECK:-}" != "1" ]] && ! has_backup; then
  if [[ ! -f "${CRM_ROOT}/tools/backup-dev-batch.sh" ]]; then
    echo "ERRORE: manca tools/backup-dev-batch.sh" >&2
    exit 1
  fi

  echo "=== PASSO 0 automatico: backup_dev (file esistenti; i nuovi saranno SKIP) ==="
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

backup_if_exists() {
  local rel="$1"
  local src="${CRM_ROOT}/${rel}"
  if [[ -f "${src}" ]]; then
    mkdir -p "${LOCAL_BACKUP}/$(dirname "${rel}")"
    cp -a "${src}" "${LOCAL_BACKUP}/${rel}"
    echo "BACKUP ${rel}"
  else
    echo "SKIP backup (nuovo) ${rel}"
  fi
}

for rel in "${FILES[@]}"; do
  backup_if_exists "${rel}"
done

echo "=== Download codice da ${BRANCH} ==="
for rel in "${FILES[@]}"; do
  download_file "${rel}"
done

echo ""
echo "=== Deploy completato ==="
echo "Poi:"
echo "  cd ${CRM_ROOT}"
echo "  php clear_cache.php && php rebuild.php"
echo "  php tools/diagnose-call-esito-opportunity.php SEDDA"
echo "  php tools/diagnose-call-esito-opportunity.php SEDDA --fix"
echo "  php tools/bonifica-call-esito-opportunity-persa.php --dry-run"
echo "  php tools/bonifica-call-esito-opportunity-persa.php"
echo ""
echo "Rollback: copiare i file da backup_dev/_sessions/*_${FIX_TAG}/ (o ${LOCAL_BACKUP}/) verso i path originali."
