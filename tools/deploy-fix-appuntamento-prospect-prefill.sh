#!/usr/bin/env bash
# Ripristina prefill prospect + durata 1h30 su crea Appuntamento (edit/editSmall).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-appuntamento-prospect-prefill-v2-9999/tools/deploy-fix-appuntamento-prospect-prefill.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/fix-appuntamento-prospect-prefill-v2-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

FILES=(
  "client/custom/src/helpers/appuntamento-prospect-sync.js"
  "client/custom/src/views/appuntamento/record/edit.js"
  "client/custom/src/views/appuntamento/record/edit-small.js"
  "client/custom/src/views/appuntamento/fields/duration.js"
)

DUPLICATES=(
  "custom/Espo/Custom/Resources/client/custom/src/views/appuntamento/record/edit.js"
  "custom/Espo/Custom/Resources/client/custom/src/views/appuntamento/record/edit-small.js"
)

echo "=== Fix Appuntamento prospect prefill + durata → ${CRM_ROOT} ==="

for rel in "${FILES[@]}"; do
  target="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${target}")"
  curl -fsSL -o "${target}" "${BASE}/${rel}?t=$(date +%s)"
  echo "OK ${rel}"
done

for rel in "${DUPLICATES[@]}"; do
  target="${CRM_ROOT}/${rel}"
  if [[ -f "${target}" ]]; then
    rm -f "${target}"
    echo "RIMOSSO duplicato ${rel}"
  fi
done

if [[ -f "${CRM_ROOT}/command.php" ]]; then
  (cd "${CRM_ROOT}" && php command.php rebuild && php command.php clearCache)
fi

echo "Fatto. Ctrl+F5 → crea Appuntamento da Prospect: CAP, fornitore, telefono, fine = inizio + 1h30."
