#!/usr/bin/env bash
# Ripristino COMPLETO stati/sottostato/esito dal branch funzionante
# cursor/appuntamento-stati-esito-9999 — NON tocca GlobalLogic 1.7.17 né taxi.
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
  # Backend: regole stato ↔ sottostato ↔ esito
  "custom/Espo/Custom/Services/AppuntamentoStatiRules.php"
  "custom/Espo/Custom/Hooks/Appuntamento/SyncStatiEsito.php"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/metadata/hooks/Appuntamento.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Appuntamento.json"
  "custom/Espo/Custom/Resources/layouts/Appuntamento/detailEsitoPopup.json"
  # Client JS (3 path Espo)
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
echo " RIPRISTINO stati/sottostato/esito"
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
grep -q 'SyncStatiEsito' \
  "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/hooks/Appuntamento.json" || {
  echo "ERRORE: hook SyncStatiEsito mancante" >&2; exit 1; }
grep -q 'appuntamento-esito' \
  "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json" || {
  echo "ERRORE: entityDefs esito senza view custom" >&2; exit 1; }
grep -q '"Pending"' \
  "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json" || {
  echo "ERRORE: sottostato enum non ripristinato" >&2; exit 1; }
grep -q 'Fuori Target' \
  "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json" && {
  echo "ERRORE: enum legacy Fuori Target ancora in entityDefs" >&2; exit 1; } || true
grep -q 'getAllowedEsiti' \
  "${CRM_ROOT}/client/custom/src/helpers/appuntamento-sottostato-map.js" || {
  echo "ERRORE: map JS incompleta" >&2; exit 1; }
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
echo "Fatto. Ctrl+Shift+R nel browser."
echo "Sottostato atteso: Pending, Gestito, Non Interessato, Chiuso Positivamente,"
echo "Annullato, Non Gestito, Non Ricevuto, Rifissato"
echo "Esito atteso: In Trattativa, Venduto Tablet, Rimandato da cliente, ecc."
