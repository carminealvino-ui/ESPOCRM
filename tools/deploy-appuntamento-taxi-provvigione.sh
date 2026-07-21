#!/usr/bin/env bash
# Deploy campo Taxi su Appuntamento + bonus provvigione 2%.
#
# Uso (salvare su disco, NON pipe):
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/appuntamento-taxi-provvigione-9999/tools/deploy-appuntamento-taxi-provvigione.sh?t=$(date +%s)" \
#     -o tools/deploy-appuntamento-taxi-provvigione.sh
#   bash tools/deploy-appuntamento-taxi-provvigione.sh

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/appuntamento-taxi-provvigione-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

FILES=(
  "custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/layouts/Appuntamento/defaultSidePanel.json"
  "custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php"
  "custom/Espo/Custom/Hooks/Appuntamento/RecalculateProvvigioniOnTaxiChange.php"
  "custom/Espo/Custom/Hooks/Provvigione/BeforeSaveLegacy.php"
  "custom/Espo/Custom/Services/ProvvigioneManager.php"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Provvigione.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/RegolaProvvigionale.json"
  "custom/Espo/Custom/Resources/i18n/en_US/Appuntamento.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Appuntamento.json"
  "custom/Espo/Custom/Resources/i18n/en_US/Provvigione.json"
  "tools/run-appuntamento-taxi-schema-patch.php"
  "tools/seed-regole-provvigioni-ariel.php"
)

echo "=== Deploy Taxi Appuntamento → ${CRM_ROOT} (branch ${BRANCH}) ==="
echo ""

cd "${CRM_ROOT}"

for rel in "${FILES[@]}"; do
  target="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${target}")"
  curl -fsSL -o "${target}" "${BASE}/${rel}?t=$(date +%s)"
  echo "OK ${rel}"
done

echo ""
echo "=== Verifica file ==="
grep -q '"taxi"' "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json" || {
  echo "ERRORE: entityDefs Appuntamento senza campo taxi" >&2
  exit 1
}
grep -q '"name": "taxi"' "${CRM_ROOT}/custom/Espo/Custom/Resources/layouts/Appuntamento/defaultSidePanel.json" || {
  echo "ERRORE: side panel senza taxi" >&2
  exit 1
}
grep -q 'bonusTaxi2\|ensureTaxiBonusProvvigione\|Bonus Taxi' "${CRM_ROOT}/custom/Espo/Custom/Services/ProvvigioneManager.php" || {
  echo "ERRORE: ProvvigioneManager senza logica Taxi" >&2
  exit 1
}
grep -q "bonusTaxi2" "${CRM_ROOT}/tools/seed-regole-provvigioni-ariel.php" || {
  echo "ERRORE: seed senza bonusTaxi2" >&2
  exit 1
}
echo "OK verifiche file"

echo ""
echo "=== Clear cache (prima del seed, per enum Bonus Taxi) ==="
if [[ -f "${CRM_ROOT}/clear_cache.php" ]]; then
  php clear_cache.php || true
elif [[ -f "${CRM_ROOT}/rebuild.php" ]]; then
  php rebuild.php || true
else
  echo "WARN: clear_cache/rebuild non trovati"
fi

echo ""
echo "=== Schema patch colonna taxi ==="
php tools/run-appuntamento-taxi-schema-patch.php

echo ""
echo "=== Seed regola bonusTaxi2 ==="
php tools/seed-regole-provvigioni-ariel.php

echo ""
echo "=== Clear cache finale ==="
if [[ -f "${CRM_ROOT}/clear_cache.php" ]]; then
  php clear_cache.php || true
elif [[ -f "${CRM_ROOT}/rebuild.php" ]]; then
  php rebuild.php || true
fi

echo ""
echo "=== Deploy Taxi completato ==="
echo "Il checkbox Taxi appare nel pannello laterale Appuntamento."
echo "Se Taxi è già attivo su appuntamenti con contratto, ricalcola le provvigioni dal contratto."
