#!/usr/bin/env bash
# Ripristina precompilazione Prospect su Crea Appuntamento + durata default 1h30.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-appuntamento-prospect-prefill-9999/tools/deploy-fix-appuntamento-prospect-prefill.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/fix-appuntamento-prospect-prefill-9999"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)

FILES=(
  "custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/layouts/Appuntamento/detailSmall.json"
  "custom/Espo/Custom/Resources/layouts/Appuntamento/defaultSidePanel.json"
  "custom/Espo/Custom/Resources/layouts/Appuntamento/detail.json"
  "client/custom/src/helpers/appuntamento-prospect-sync.js"
  "client/custom/src/views/appuntamento/record/edit.js"
  "client/custom/src/views/appuntamento/record/edit-small.js"
  "client/custom/src/views/appuntamento/fields/duration.js"
)

echo "=== Hotfix Appuntamento: prefill Prospect + durata 1h30 ==="
echo "Branch: ${BRANCH}"
echo "CRM:    ${CRM_ROOT}"
echo ""

for rel in "${FILES[@]}"; do
  dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=${STAMP}" -o "${dest}"
  echo "OK ${rel}"
  if [[ "${rel}" == *.json ]]; then
    php -r "json_decode(file_get_contents('${dest}')); if (json_last_error()) { fwrite(STDERR, json_last_error_msg()); exit(1); }"
    echo "JSON OK ${rel}"
  fi
done

echo ""
(cd "${CRM_ROOT}" && php clear_cache.php && php rebuild.php)
echo ""
echo "Fatto. Ctrl+F5 nel browser, poi Crea Appuntamento da Prospect."
echo "Verifica: CAP/Telefono/Fornitore compilati, durata = 1h 30m."
