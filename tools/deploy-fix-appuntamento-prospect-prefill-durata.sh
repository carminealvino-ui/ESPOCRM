#!/usr/bin/env bash
# Ripristina prefill Prospect (Fornitore/Brand/Categoria) SENZA toccare la durata UTC 1h30.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-appuntamento-prospect-prefill-durata-9999/tools/deploy-fix-appuntamento-prospect-prefill-durata.sh?t=$(date +%s)" | bash
#   php clear_cache.php && php rebuild.php

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/fix-appuntamento-prospect-prefill-durata-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"
TS="$(date +%s)"

if [[ ! -d "${CRM_ROOT}" ]]; then
  echo "ERRORE: cartella CRM non trovata: ${CRM_ROOT}" >&2
  exit 1
fi

cd "${CRM_ROOT}"

fetch() {
  local path="$1"
  mkdir -p "$(dirname "${path}")"
  curl -fL --retry 8 --retry-all-errors --retry-delay 2 --retry-max-time 120 \
    "${BASE}/${path}?t=${TS}" -o "${path}"
  echo "OK ${path}"
}

# Deploy sul path LIVE + mirror (alcune installazioni caricano da custom/Espo/Custom/...)
deploy_client() {
  local rel="$1"
  local suffix="${rel#client/custom/}"
  fetch "${rel}"
  mkdir -p "custom/Espo/Custom/client/custom/$(dirname "${suffix}")"
  mkdir -p "custom/Espo/Custom/Resources/client/custom/$(dirname "${suffix}")"
  cp "${rel}" "custom/Espo/Custom/client/custom/${suffix}"
  cp "${rel}" "custom/Espo/Custom/Resources/client/custom/${suffix}"
  echo "OK mirror ${suffix}"
}

echo "=== Prefill Prospect + durata UTC (${BRANCH}) ==="

fetch custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json
fetch custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json

deploy_client client/custom/src/helpers/appuntamento-prospect-sync.js
deploy_client client/custom/src/views/fields/appuntamento-parent.js
deploy_client client/custom/src/views/fields/product-brand-by-partner.js
deploy_client client/custom/src/views/fields/appuntamento-duration.js
deploy_client client/custom/src/views/appuntamento/record/edit-small.js
deploy_client client/custom/src/views/appuntamento/record/edit.js

echo "=== Verifica ==="
JS="client/custom/src/helpers/appuntamento-prospect-sync.js"
EDIT="client/custom/src/views/appuntamento/record/edit-small.js"
META="custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json"

if grep -q "prospect-prefill-durata-v1" "${JS}" \
  && grep -q "setupProspectSync" "${EDIT}" \
  && grep -q "addSecondsUtc\|moment.utc" "${EDIT}" \
  && grep -q "appuntamento-parent" "${META}"; then
  echo "VERIFICA OK"
else
  echo "ATTENZIONE: verifica manuale file deployati" >&2
fi

echo "=== Fine. Esegui: php clear_cache.php && php rebuild.php  poi Ctrl+Shift+R ==="
