#!/usr/bin/env bash
# Lista Contatti Telefonici: Esito del richiamo + Opportunità collegata.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/COMMIT/tools/deploy-call-lista-esito.sh" \
#     -o tools/deploy-call-lista-esito.sh
#   bash tools/deploy-call-lista-esito.sh

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
COMMIT="${DEPLOY_COMMIT:-REPLACE_COMMIT}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${COMMIT}"
FIX_TAG="call-lista-esito"
STAMP=$(date +%Y%m%d-%H%M%S)

cd "${CRM_ROOT}"

echo "=== Deploy Call lista esito+opportunità → ${CRM_ROOT} ==="
echo "COMMIT=${COMMIT}"

FILES=(
  "custom/Espo/Custom/Resources/layouts/Call/list.json"
  "custom/Espo/Custom/Resources/layouts/Call/defaultSidePanel.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Call.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Opportunity.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Call.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Opportunity.json"
  "custom/Espo/Custom/Services/CallOpportunityLinker.php"
  "custom/Espo/Custom/Services/AppuntamentoPendingCallCreator.php"
  "custom/Espo/Custom/Hooks/Call/LinkOpportunity.php"
  "tools/backfill-call-opportunity-link.php"
)

has_backup() {
  local sessions="${CRM_ROOT}/backup_dev/_sessions"
  [[ -d "${sessions}" ]] || return 1
  local latest
  latest="$(find "${sessions}" -maxdepth 1 -type d -name "*_${FIX_TAG}" 2>/dev/null | sort -r | head -1)"
  [[ -n "${latest}" && -f "${latest}/manifest.txt" && -f "${latest}/files.list" ]]
}

mkdir -p tools/backup-manifests
printf '%s\n' "${FILES[@]}" > "tools/backup-manifests/${FIX_TAG}.files"

if [[ "${SKIP_BACKUP_CHECK:-}" != "1" ]] && ! has_backup; then
  bash tools/backup-dev-batch.sh "${FIX_TAG}" --manifest "tools/backup-manifests/${FIX_TAG}.files"
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

for rel in "${FILES[@]}"; do
  dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=${STAMP}" -o "${dest}"
  echo "OK ${rel}"
done

echo ""
echo "=== Deploy completato ==="
echo "Poi:"
echo "  php clear_cache.php && php rebuild.php"
echo "  php tools/backfill-call-opportunity-link.php --dry-run"
echo "  php tools/backfill-call-opportunity-link.php"
echo "Poi hard-refresh elenco Contatti Telefonici."
