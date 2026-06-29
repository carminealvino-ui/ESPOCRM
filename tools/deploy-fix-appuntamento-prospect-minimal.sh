#!/usr/bin/env bash
# Fix minimo: fieldViews parent + sync Prospect + durata 1h30 (senza helper extra).
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
)

CLIENT=(
  "client/custom/src/views/fields/appuntamento-parent.js"
  "client/custom/src/views/appuntamento/record/edit-small.js"
)

echo "=== Fix minimo Appuntamento da Prospect → ${CRM_ROOT} ==="

for rel in "${METADATA[@]}"; do
  target="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${target}")"
  curl -fsSL -o "${target}" "${BASE}/${rel}?t=$(date +%s)"
  echo "OK ${rel}"
done

for rel in "${CLIENT[@]}"; do
  TMP="$(mktemp)"
  curl -fsSL -o "${TMP}" "${BASE}/${rel}?t=$(date +%s)"
  suffix="${rel#client/custom/}"

  for target in \
    "${CRM_ROOT}/${rel}" \
    "${CRM_ROOT}/custom/Espo/Custom/Resources/client/custom/${suffix}" \
    "${CRM_ROOT}/custom/Espo/Custom/client/custom/${suffix}"; do
    mkdir -p "$(dirname "${target}")"
    cp "${TMP}" "${target}"
    echo "OK ${target#${CRM_ROOT}/}"
  done

  rm -f "${TMP}"
done

if ! grep -q "VERSION = '1.2.6'" "${CRM_ROOT}/client/custom/src/views/fields/appuntamento-parent.js"; then
  echo "ERRORE: appuntamento-parent.js non aggiornato" >&2
  exit 1
fi

if ! grep -q "fieldViews" "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json"; then
  echo "ERRORE: fieldViews mancante in clientDefs" >&2
  exit 1
fi

(cd "${CRM_ROOT}" && php command.php rebuild && php command.php clearCache)

echo "Fatto. Ctrl+Shift+R → Crea Appuntamento da Prospect."
