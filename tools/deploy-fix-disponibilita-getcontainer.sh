#!/usr/bin/env bash
# Fix 500 su Genera Disponibilità Ricorrenti (getContainer non esiste in Espo 10).
#
# Uso:
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-disponibilita-getcontainer-9999/tools/deploy-fix-disponibilita-getcontainer.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/fix-disponibilita-getcontainer-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"
TS="$(date +%s)"

if [[ ! -d "${CRM_ROOT}" ]]; then
  echo "ERRORE: cartella CRM non trovata: ${CRM_ROOT}" >&2
  exit 1
fi

cd "${CRM_ROOT}"

PHP_BIN="${PHP_BIN:-php}"
if ! command -v "${PHP_BIN}" >/dev/null 2>&1; then
  echo "ERRORE: php non trovato. Imposta PHP_BIN=/percorso/php" >&2
  exit 1
fi

echo "=== Fix Disponibilita getContainer (Espo 10) ==="
echo "CRM_ROOT=${CRM_ROOT}"

fetch() {
  local path="$1"
  mkdir -p "$(dirname "${path}")"
  if ! curl -fL --retry 8 --retry-all-errors --retry-delay 2 --retry-max-time 120 \
    "${BASE}/${path}?t=${TS}" -o "${path}"; then
    echo "ERRORE fetch: ${BASE}/${path}" >&2
    exit 1
  fi
  echo "OK ${path}"
}

fetch custom/Espo/Custom/Controllers/Disponibilita.php

"${PHP_BIN}" clear_cache.php
"${PHP_BIN}" rebuild.php
"${PHP_BIN}" clear_cache.php

echo ""
echo "=== Fatto ==="
echo "Riprova Disponibilità → Disponibilità Ricorrenti → Genera."
