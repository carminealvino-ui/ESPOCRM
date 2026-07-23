#!/usr/bin/env bash
# Fix Ingestibile: sottostato + esito selezionabili.
#
# Prima: backup
#   bash tools/backup-dev-batch.sh ingestibile-sottostato \
#     --manifest tools/backup-manifests/appuntamento-baseline-produzione.files
#
# Deploy:
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-ingestibile-sottostato-esito-9999/tools/deploy-fix-ingestibile-sottostato-esito.sh?t=$(date +%s)" \
#     -o tools/deploy-fix-ingestibile-sottostato-esito.sh
#   bash tools/deploy-fix-ingestibile-sottostato-esito.sh
#
# Poi Ctrl+Shift+R e export-delta → push main.

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/fix-ingestibile-sottostato-esito-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

FILES=(
  "custom/Espo/Custom/Services/AppuntamentoStatiRules.php"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Appuntamento.json"
  "client/custom/src/helpers/appuntamento-sottostato-map.js"
  "client/custom/src/views/fields/appuntamento-esito.js"
  "custom/Espo/Custom/client/custom/src/helpers/appuntamento-sottostato-map.js"
  "custom/Espo/Custom/client/custom/src/views/fields/appuntamento-esito.js"
  "custom/Espo/Custom/Resources/client/custom/src/helpers/appuntamento-sottostato-map.js"
  "custom/Espo/Custom/Resources/client/custom/src/views/fields/appuntamento-esito.js"
  "tools/verify-appuntamento-baseline.php"
  "tools/pre-deploy-check-appuntamento.sh"
)

echo "=============================================="
echo " Fix Ingestibile sottostato/esito"
echo " Branch: ${BRANCH}"
echo " CRM:    ${CRM_ROOT}"
echo "=============================================="

cd "${CRM_ROOT}"

for rel in "${FILES[@]}"; do
  target="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${target}")"
  curl -fsSL -o "${target}" "${BASE}/${rel}?t=$(date +%s)"
  [[ "${rel}" == *.sh ]] && chmod +x "${target}"
  echo "OK ${rel}"
done

echo ""
echo "=== Verifiche ==="
grep -q 'Infattibilità Tecnica' \
  "${CRM_ROOT}/custom/Espo/Custom/Services/AppuntamentoStatiRules.php" || {
  echo "ERRORE: Rules senza sottostati Ingestibile" >&2; exit 1; }
grep -q 'Infattibilità Tecnica' \
  "${CRM_ROOT}/client/custom/src/helpers/appuntamento-sottostato-map.js" || {
  echo "ERRORE: map JS senza sottostati Ingestibile" >&2; exit 1; }
grep -q 'Infattibilità Tecnica' \
  "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json" || {
  echo "ERRORE: entityDefs senza sottostati Ingestibile" >&2; exit 1; }
bash tools/pre-deploy-check-appuntamento.sh "${CRM_ROOT}"
php tools/verify-appuntamento-baseline.php || true

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
echo "Con Stato=Ingestibile devono essere selezionabili:"
echo "  Sottostato: Infattibilità Tecnica, Solo Informazioni, Prodotto non Conforme, Fuori Target"
echo "  Esito: Solo Preventivo, Non Finanziabile, ..."
echo "Poi: php tools/sync-custom-prod-repo.php export-delta --branch=main"
