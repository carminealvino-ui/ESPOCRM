#!/usr/bin/env bash
# Ripristina prefill prospect + durata 1h30 (tutti i path client, no duplicati obsoleti).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-appuntamento-prospect-prefill-v2-9999/tools/deploy-fix-appuntamento-prospect-prefill.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/fix-appuntamento-prospect-prefill-v2-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

METADATA_FILES=(
  "custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json"
)

CLIENT_FILES=(
  "client/custom/src/helpers/appuntamento-prospect-sync.js"
  "client/custom/src/views/appuntamento/record/edit.js"
  "client/custom/src/views/appuntamento/record/edit-small.js"
  "client/custom/src/views/appuntamento/fields/duration.js"
  "client/custom/src/views/fields/appuntamento-parent.js"
)

STALE_RECORD_DUPLICATES=(
  "custom/Espo/Custom/Resources/client/custom/src/views/appuntamento/record/edit.js"
  "custom/Espo/Custom/Resources/client/custom/src/views/appuntamento/record/edit-small.js"
  "custom/Espo/Custom/client/custom/src/views/appuntamento/record/edit.js"
  "custom/Espo/Custom/client/custom/src/views/appuntamento/record/edit-small.js"
)

echo "=== Fix Appuntamento prospect prefill + durata → ${CRM_ROOT} ==="

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
    chmod 644 "${target}" 2>/dev/null || true
    echo "OK ${target#${CRM_ROOT}/}"
  done
}

for rel in "${METADATA_FILES[@]}"; do
  target="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${target}")"
  curl -fsSL -o "${target}" "${BASE}/${rel}?t=$(date +%s)"
  echo "OK ${rel}"
done

for rel in "${CLIENT_FILES[@]}"; do
  TMP="$(mktemp)"
  curl -fsSL -o "${TMP}" "${BASE}/${rel}?t=$(date +%s)"
  deploy_client_file "${rel}" "${TMP}"
  rm -f "${TMP}"
done

for rel in "${STALE_RECORD_DUPLICATES[@]}"; do
  target="${CRM_ROOT}/${rel}"
  if [[ -f "${target}" ]]; then
    rm -f "${target}"
    echo "RIMOSSO duplicato obsoleto ${rel}"
  fi
done

VERIFY="${CRM_ROOT}/client/custom/src/views/fields/appuntamento-parent.js"
if ! grep -q "VERSION = '1.3.0'" "${VERIFY}"; then
  echo "ERRORE: appuntamento-parent.js non aggiornato" >&2
  exit 1
fi

if [[ -f "${CRM_ROOT}/command.php" ]]; then
  (cd "${CRM_ROOT}" && php command.php rebuild && php command.php clearCache)
fi

echo "Fatto. Ctrl+Shift+R → crea Appuntamento da Prospect."
