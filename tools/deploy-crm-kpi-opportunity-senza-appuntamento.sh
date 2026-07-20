#!/usr/bin/env bash
# KPI CRM: alert "Senza appuntamento" in card Opportunità.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/COMMIT/tools/deploy-crm-kpi-opportunity-senza-appuntamento.sh" \
#     -o tools/deploy-crm-kpi-opportunity-senza-appuntamento.sh
#   bash tools/deploy-crm-kpi-opportunity-senza-appuntamento.sh

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
COMMIT="${DEPLOY_COMMIT:-fe148850a78c47489a2d3a48fdaf09b021be9992}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${COMMIT}"
FIX_TAG="crm-kpi-opportunity-senza-appuntamento"
STAMP=$(date +%Y%m%d-%H%M%S)

cd "${CRM_ROOT}"

echo "=== Deploy KPI: Opportunità senza appuntamento ==="
echo "COMMIT=${COMMIT}"

FILES=(
  "custom/Espo/Custom/Classes/Select/Opportunity/PrimaryFilters/SenzaAppuntamento.php"
  "custom/Espo/Custom/Resources/metadata/selectDefs/Opportunity.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Opportunity.json"
  "custom/Espo/Custom/Tools/CrmKpi/Alerts.php"
)

mkdir -p tools/backup-manifests
printf '%s\n' "${FILES[@]}" > "tools/backup-manifests/${FIX_TAG}.files"

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

if ! grep -q "opportunityWithoutAppuntamento" custom/Espo/Custom/Tools/CrmKpi/Alerts.php; then
  echo "ERRORE: alert opportunityWithoutAppuntamento mancante" >&2
  exit 1
fi

if ! grep -q "senzaAppuntamento" custom/Espo/Custom/Resources/metadata/selectDefs/Opportunity.json; then
  echo "ERRORE: primaryFilter senzaAppuntamento mancante" >&2
  exit 1
fi

echo "=== Cache ==="
rm -rf data/cache/* 2>/dev/null || true
php clear_cache.php || true
php rebuild.php

echo ""
echo "=== Fine ==="
echo "Nella dashlet KPI → card Opportunità: voce 'Senza appuntamento' (~294)."
echo "Click → lista Opportunità filtrata."
