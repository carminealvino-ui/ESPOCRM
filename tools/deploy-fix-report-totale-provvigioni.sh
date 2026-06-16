#!/usr/bin/env bash
# Fix report Provvigioni Totali: sync campo totaleProvvigioni sui contratti.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-report-totale-provvigioni-9999/tools/deploy-fix-report-totale-provvigioni.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/fix-report-totale-provvigioni-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)
LOCAL_BACKUP="${CRM_ROOT}/backup/fix-report-totale-provvigioni/server-${STAMP}"

echo "=== Backup locale pre-deploy in ${LOCAL_BACKUP} ==="
mkdir -p "${LOCAL_BACKUP}"

backup_if_exists() {
  local rel="$1"
  local src="${CRM_ROOT}/${rel}"
  if [[ -f "${src}" ]]; then
    mkdir -p "${LOCAL_BACKUP}/$(dirname "${rel}")"
    cp -a "${src}" "${LOCAL_BACKUP}/${rel}"
    echo "BACKUP ${rel}"
  fi
}

FILES=(
  "custom/Espo/Custom/Services/QuoteProvvigioniSync.php"
  "custom/Espo/Custom/Hooks/Provvigione/SyncQuoteTotaleProvvigioni.php"
  "custom/Espo/Custom/Hooks/Provvigione/BeforeSave.php"
  "custom/Espo/Custom/Resources/metadata/hooks/Provvigione.json"
  "tools/backfill-quote-totale-provvigioni.php"
)

for rel in "${FILES[@]}"; do
  backup_if_exists "${rel}"
done

echo "=== Download da ${BRANCH} ==="
for rel in "${FILES[@]}"; do
  dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=${STAMP}" -o "${dest}"
  echo "OK ${rel}"
done

echo ""
echo "=== Prossimo passo (sul server) ==="
echo "  cd ${CRM_ROOT} && php clear_cache.php && php rebuild.php"
echo "  cd ${CRM_ROOT} && php tools/backfill-quote-totale-provvigioni.php --verbose"
echo ""
echo "Poi riapri il report «Vendite Mese - Totale Provvigioni»."
