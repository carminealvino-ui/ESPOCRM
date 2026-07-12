#!/usr/bin/env bash
# Layout modal Crea Opportunità: rimuove statoContratto, aggiunge importoCaparra sotto importo.
#
# Uso:
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-crea-opportunita-caparra-9999/tools/deploy-crea-opportunita-caparra.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/fix-crea-opportunita-caparra-9999"
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

echo "=== Layout Crea Opportunità (importo caparra) ==="
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

fetch custom/Espo/Custom/Resources/layouts/Opportunity/detailSmall.json

"${PHP_BIN}" clear_cache.php
"${PHP_BIN}" rebuild.php
"${PHP_BIN}" clear_cache.php

echo ""
echo "=== Fatto ==="
echo "  - Rimosso Stato Contratto dal modal Crea Opportunità"
echo "  - Aggiunto Importo Caparra sotto Importo (Formulazione Offerta)"
echo ""
echo "Ricarica con Ctrl+F5."
