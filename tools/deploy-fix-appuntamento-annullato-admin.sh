#!/usr/bin/env bash
# Fix riassegnazione admin su appuntamento annullato (Not Held).
#
# Uso:
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-appuntamento-annullato-admin-9999/tools/deploy-fix-appuntamento-annullato-admin.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/fix-appuntamento-annullato-admin-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"
TS="$(date +%s)"

if [[ ! -d "${CRM_ROOT}" ]]; then
  echo "ERRORE: cartella CRM non trovata: ${CRM_ROOT}" >&2
  exit 1
fi

cd "${CRM_ROOT}"

PHP_BIN="${PHP_BIN:-php}"

fetch() {
  local path="$1"
  mkdir -p "$(dirname "${path}")"
  curl -fL --retry 8 --retry-all-errors --retry-delay 2 --retry-max-time 120 \
    "${BASE}/${path}?t=${TS}" -o "${path}"
  echo "OK ${path}"
}

FILES=(
  custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php
  custom/Espo/Custom/Services/AppuntamentoGoogleSync.php
  tools/bonifica-appuntamento-not-held-admin.php
)

for rel in "${FILES[@]}"; do
  fetch "${rel}"
done

"${PHP_BIN}" clear_cache.php
"${PHP_BIN}" rebuild.php
"${PHP_BIN}" clear_cache.php

echo ""
echo "=== Bonifica appuntamenti già annullati (opzionale) ==="
if [[ -f tools/bonifica-appuntamento-not-held-admin.php ]]; then
  "${PHP_BIN}" tools/bonifica-appuntamento-not-held-admin.php --dry-run
  echo "  php tools/bonifica-appuntamento-not-held-admin.php --apply"
fi

echo ""
echo "Fatto. Salva di nuovo l'appuntamento annullato oppure esegui la bonifica."
