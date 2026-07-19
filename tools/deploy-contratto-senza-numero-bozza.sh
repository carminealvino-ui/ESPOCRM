#!/usr/bin/env bash
# Contratto senza Numero → Stato Bozza; KPI come sospeso.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/COMMIT/tools/deploy-contratto-senza-numero-bozza.sh" \
#     -o tools/deploy-contratto-senza-numero-bozza.sh
#   bash tools/deploy-contratto-senza-numero-bozza.sh

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
COMMIT="${DEPLOY_COMMIT:-72b1e7ea345a86868432ca1fbe54d6ab24afccde}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${COMMIT}"
FIX_TAG="contratto-senza-numero-bozza"
STAMP=$(date +%Y%m%d-%H%M%S)

cd "${CRM_ROOT}"

echo "=== Deploy contratto senza numero → Bozza/KPI sospeso ==="
echo "COMMIT=${COMMIT}"

FILES=(
  "custom/Espo/Custom/Hooks/Quote/SetPresentedWhenNumeroContratto.php"
  "custom/Espo/Custom/Services/CrmKpi/CrmKpiService.php"
  "custom/Espo/Custom/Classes/Select/Quote/PrimaryFilters/ContrattiSospesiOrdini.php"
  "custom/Espo/Custom/Classes/Select/Quote/PrimaryFilters/ContrattiBacklog.php"
  "tools/bonifica-quote-senza-numero-bozza.php"
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
echo "  php tools/bonifica-quote-senza-numero-bozza.php --dry-run"
echo "  php tools/bonifica-quote-senza-numero-bozza.php"
