#!/usr/bin/env bash
# Fix definitivo prefill Prospect + durata 1h30 (3 path client, rimuove duplicati AMD).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-appuntamento-prospect-minimal-9999/tools/deploy-fix-appuntamento-prospect-minimal.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/fix-appuntamento-prospect-minimal-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

METADATA=(
  "custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json"
)

CLIENT=(
  "client/custom/src/helpers/appuntamento-prospect-sync.js"
  "client/custom/src/views/fields/appuntamento-parent.js"
  "client/custom/src/views/appuntamento/record/edit.js"
  "client/custom/src/views/appuntamento/record/edit-small.js"
  "client/custom/src/views/appuntamento/fields/duration.js"
)

echo "=== Fix definitivo Appuntamento da Prospect → ${CRM_ROOT} ==="

for rel in "${METADATA[@]}"; do
  target="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${target}")"
  curl -fsSL -o "${target}" "${BASE}/${rel}?t=$(date +%s)"
  echo "OK ${rel}"
done

deploy_client_file() {
  local rel="$1"
  local tmp="$2"
  local suffix="${rel#client/custom/}"
  local target

  for target in \
    "${CRM_ROOT}/${rel}" \
    "${CRM_ROOT}/custom/Espo/Custom/Resources/client/custom/${suffix}" \
    "${CRM_ROOT}/custom/Espo/Custom/client/custom/${suffix}"; do
    mkdir -p "$(dirname "${target}")"
    cp "${tmp}" "${target}"
    echo "OK ${target#${CRM_ROOT}/}"
  done
}

for rel in "${CLIENT[@]}"; do
  TMP="$(mktemp)"
  curl -fsSL -o "${TMP}" "${BASE}/${rel}?t=$(date +%s)"
  deploy_client_file "${rel}" "${TMP}"
  rm -f "${TMP}"
done

grep -q "appuntamento-prospect-sync" "${CRM_ROOT}/client/custom/src/views/appuntamento/record/edit-small.js" || {
  echo "ERRORE: edit-small non collegato al helper" >&2
  exit 1
}

grep -q "appuntamento-parent" "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json" || {
  echo "ERRORE: view parent mancante in entityDefs" >&2
  exit 1
}

(cd "${CRM_ROOT}" && php command.php rebuild && php command.php clearCache)

echo "Fatto. Ctrl+Shift+R → Crea Appuntamento da Prospect."
