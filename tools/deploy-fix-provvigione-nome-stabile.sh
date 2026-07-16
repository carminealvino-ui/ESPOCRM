#!/usr/bin/env bash
# Nome Provvigione: codice contratto - cliente - tipo - importo
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-provvigione-nome-stabile-9999/tools/deploy-fix-provvigione-nome-stabile.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/fix-provvigione-nome-stabile-9999"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)
LOCAL_BACKUP="${CRM_ROOT}/backup/fix-provvigione-nome-stabile/server-${STAMP}"

echo "=== Backup in ${LOCAL_BACKUP} ==="
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
  "custom/Espo/Custom/Hooks/Provvigione/AccrualAndAmount.php"
  "custom/Espo/Custom/Services/ProvvigioneManager.php"
  "tools/backfill-provvigioni-nomi.php"
)

for rel in "${FILES[@]}"; do
  backup_if_exists "${rel}"
done

echo "=== Deploy da ${BRANCH} ==="
for rel in "${FILES[@]}"; do
  mkdir -p "${CRM_ROOT}/$(dirname "${rel}")"
  curl -fsSL "${BASE}/${rel}?t=$(date +%s)" -o "${CRM_ROOT}/${rel}"
  echo "OK ${rel}"
done

cd "${CRM_ROOT}"
php clear_cache.php
php rebuild.php

echo "=== Backfill nomi ==="
php tools/backfill-provvigioni-nomi.php

php clear_cache.php

HOOK="${CRM_ROOT}/custom/Espo/Custom/Hooks/Provvigione/AccrualAndAmount.php"
MGR="${CRM_ROOT}/custom/Espo/Custom/Services/ProvvigioneManager.php"
if grep -q 'codice contratto - nome cliente - tipo provvigione - importo' "${HOOK}" \
  && grep -q 'codice contratto - nome cliente - tipo provvigione - importo' "${MGR}"; then
  echo "VERIFICA OK: formato nome con codice + importo"
else
  echo "ATTENZIONE: verifica manuale hook/manager"
fi

echo "=== Fine. Ctrl+Shift+R in browser ==="
