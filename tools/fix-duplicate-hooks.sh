#!/usr/bin/env bash
# Quarantena file hook duplicati / backup in custom/Espo/Custom/Hooks
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL ".../tools/fix-duplicate-hooks.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/fix-appuntamento-calendario-500-9999"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)
HOOKS="${CRM_ROOT}/custom/Espo/Custom/Hooks"
QUARANTINE="${CRM_ROOT}/backup_dev/hooks_quarantine/${STAMP}"

echo "=== Fix hook duplicati ==="

mkdir -p "${QUARANTINE}"

# File InvitoAFatturare aggiornato (classe InvitoBeforeSave)
mkdir -p "${CRM_ROOT}/custom/Espo/Custom/Hooks/InvitoAFatturare"
curl -fsSL "${BASE}/custom/Espo/Custom/Hooks/InvitoAFatturare/BeforeSave.php?t=${STAMP}" \
  -o "${CRM_ROOT}/custom/Espo/Custom/Hooks/InvitoAFatturare/BeforeSave.php"
echo "OK InvitoAFatturare/BeforeSave.php (InvitoBeforeSave)"

# Quarantena backup/copy dentro Hooks
MOVED=0

while IFS= read -r -d '' file; do
  rel="${file#${HOOKS}/}"
  dest="${QUARANTINE}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  mv "${file}" "${dest}"
  echo "QUARANTINE ${rel}"
  MOVED=$((MOVED + 1))
done < <(find "${HOOKS}" -type f \( \
  -iname '*.bak' -o -iname '*.old' -o -iname '*.backup' -o -iname '*.orig' -o \
  -iname '*.save' -o -iname '*.copy' -o -iname '*~' -o \
  -iname 'backup-*' -o -iname 'copy-*' \
  \) -print0 2>/dev/null || true)

# Duplicati espliciti noti
for extra in \
  "${HOOKS}/InvitoAFatturare/BeforeSave.php.bak" \
  "${HOOKS}/InvitoAFatturare/BeforeSave.bak.php" \
  "${HOOKS}/InvitoAFatturare/backup-BeforeSave.php"
do
  if [[ -f "${extra}" ]]; then
    rel="${extra#${HOOKS}/}"
    dest="${QUARANTINE}/${rel}"
    mkdir -p "$(dirname "${dest}")"
    mv "${extra}" "${dest}"
    echo "QUARANTINE ${rel}"
    MOVED=$((MOVED + 1))
  fi
done

echo ""
echo "File spostati in quarantena: ${MOVED}"
echo "Quarantena: ${QUARANTINE}"

echo ""
php "${CRM_ROOT}/tools/diagnose-duplicate-hooks.php" || true

echo ""
cd "${CRM_ROOT}"
php clear_cache.php 2>/dev/null || true
rm -rf data/cache/* 2>/dev/null || true
php -r "if (function_exists('opcache_reset')) { opcache_reset(); echo \"opcache_reset OK\n\"; }" 2>/dev/null || true

echo ""
echo "Poi: php tools/diagnose-appuntamento-save.php"
