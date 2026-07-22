#!/usr/bin/env bash
# Deploy SOLO UI stati/sottostato/esito filtrati su Appuntamento.
#
# Perché serve: senza questi file il form calendario (Modifica Appuntamento)
# mostra i vecchi enum completi invece dei menu filtrati per stato/sottostato.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-appuntamento-stati-esito-ui-9999/tools/deploy-appuntamento-stati-esito-ui.sh?t=$(date +%s)" \
#     -o tools/deploy-appuntamento-stati-esito-ui.sh
#   bash tools/deploy-appuntamento-stati-esito-ui.sh
#
# Poi Ctrl+Shift+R nel browser.

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/fix-appuntamento-stati-esito-ui-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

FILES=(
  "custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/layouts/Appuntamento/detailEsitoPopup.json"
  "client/custom/src/helpers/appuntamento-sottostato-map.js"
  "client/custom/src/views/fields/appuntamento-sottostato.js"
  "client/custom/src/views/fields/appuntamento-sottostato-popup.js"
  "client/custom/src/views/fields/appuntamento-esito.js"
  "custom/Espo/Custom/client/custom/src/helpers/appuntamento-sottostato-map.js"
  "custom/Espo/Custom/client/custom/src/views/fields/appuntamento-sottostato.js"
  "custom/Espo/Custom/client/custom/src/views/fields/appuntamento-sottostato-popup.js"
  "custom/Espo/Custom/client/custom/src/views/fields/appuntamento-esito.js"
  "custom/Espo/Custom/Resources/client/custom/src/helpers/appuntamento-sottostato-map.js"
  "custom/Espo/Custom/Resources/client/custom/src/views/fields/appuntamento-sottostato.js"
  "custom/Espo/Custom/Resources/client/custom/src/views/fields/appuntamento-sottostato-popup.js"
  "custom/Espo/Custom/Resources/client/custom/src/views/fields/appuntamento-esito.js"
)

echo "=============================================="
echo " UI stati/sottostato/esito Appuntamento"
echo " Branch: ${BRANCH}"
echo " CRM:    ${CRM_ROOT}"
echo "=============================================="
echo ""

cd "${CRM_ROOT}"

for rel in "${FILES[@]}"; do
  target="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${target}")"
  curl -fsSL -o "${target}" "${BASE}/${rel}?t=$(date +%s)"
  echo "OK ${rel}"
done

echo ""
echo "=== Verifiche ==="
grep -q 'appuntamento-esito' \
  "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json" || {
  echo "ERRORE: clientDefs Appuntamento senza fieldViews esito" >&2; exit 1; }
grep -q 'getAllowedEsiti' \
  "${CRM_ROOT}/client/custom/src/helpers/appuntamento-sottostato-map.js" || {
  echo "ERRORE: map esito incompleta" >&2; exit 1; }
echo "OK verifiche"

if [[ -f clear_cache.php ]]; then
  php clear_cache.php || true
fi
if [[ -f rebuild.php ]]; then
  php rebuild.php || true
elif [[ -f command.php ]]; then
  php command.php clearCache || true
fi

echo ""
echo "Fatto. Ctrl+Shift+R nel browser per ricaricare i JS client."
