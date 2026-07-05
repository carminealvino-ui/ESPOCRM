#!/usr/bin/env bash
# Ripristino urgente CRM 500 (Settings/I18n) — backup + fix controller Espo 10.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/invito-a-fatturare-selezione-9999/tools/deploy-crm-ripristino-500.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/invito-a-fatturare-selezione-9999"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)
BACKUP="${CRM_ROOT}/backup_dev/crm-ripristino-500-${STAMP}"

echo "=== Backup ${BACKUP} ==="
mkdir -p "${BACKUP}"

FILES=(
  "custom/Espo/Custom/Controllers/CallStandardTesto.php"
  "custom/Espo/Custom/Controllers/InvitoAFatturare.php"
  "custom/Espo/Custom/Controllers/Appuntamento.php"
  "tools/diagnose-controllers.php"
)

for rel in "${FILES[@]}"; do
  src="${CRM_ROOT}/${rel}"
  if [[ -f "${src}" ]]; then
    mkdir -p "${BACKUP}/$(dirname "${rel}")"
    cp -a "${src}" "${BACKUP}/${rel}"
    echo "BACKUP ${rel}"
  fi
done

echo ""
echo "=== Download fix ==="
for rel in "${FILES[@]}"; do
  dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  if curl -fsSL "${BASE}/${rel}?t=${STAMP}" -o "${dest}" 2>/dev/null; then
    echo "OK ${rel}"
  else
    echo "SKIP ${rel} (non in branch)"
  fi
done

echo ""
echo "=== Diagnostica ==="
cd "${CRM_ROOT}"
php tools/diagnose-controllers.php || true

echo ""
echo "=== Pulizia cache ==="
php clear_cache.php 2>/dev/null || true
rm -rf data/cache/* 2>/dev/null || true
php -r "if (function_exists('opcache_reset')) { opcache_reset(); echo \"opcache_reset OK\n\"; }" 2>/dev/null || true

echo ""
echo "Fatto. Ricarica CRM con Ctrl+Shift+R (finestra anonima)."
echo "Se ancora 500: tail -50 data/logs/espo-\$(date +%Y-%m-%d).log | grep ERROR"
