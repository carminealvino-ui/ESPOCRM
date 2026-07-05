#!/usr/bin/env bash
# Fix Internal Server Error su Salva Appuntamento da calendario.
# Include: GlobalLogic Espo 10, Provvigione forecast, Prospect 409, sottostato, servizi.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-appuntamento-calendario-500-9999/tools/deploy-fix-appuntamento-calendario-500.sh?t=$(date +%s)" | bash
#   php clear_cache.php && rm -rf data/cache/*

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/fix-appuntamento-calendario-500-9999"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)
BACKUP="${CRM_ROOT}/backup_dev/Appuntamento/calendario-500-${STAMP}"

FILES=(
  "custom/Espo/Custom/Hooks/Appuntamento/RequiredDefaults.php"
  "custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php"
  "custom/Espo/Custom/Hooks/Appuntamento/ProvvigioneForecast.php"
  "custom/Espo/Custom/Services/ProvvigioneManager.php"
  "custom/Espo/Custom/Services/Prospect.php"
  "custom/Espo/Custom/Services/Appuntamento.php"
  "custom/Espo/Custom/Services/AppuntamentoRifissatoCreator.php"
  "custom/Espo/Custom/Controllers/Appuntamento.php"
  "custom/Espo/Custom/Services/AppuntamentoPendingCallCreator.php"
  "custom/Espo/Custom/Tools/Appuntamento/PendingCallDateTime.php"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json"
  "client/custom/src/helpers/appuntamento-prospect-sync.js"
  "client/custom/src/helpers/appuntamento-sottostato-map.js"
  "client/custom/src/views/fields/appuntamento-parent.js"
  "client/custom/src/views/fields/appuntamento-sottostato.js"
  "client/custom/src/views/fields/appuntamento-sottostato-popup.js"
  "client/custom/src/views/appuntamento/record/edit-small.js"
  "client/custom/src/views/appuntamento/popup-notification.js"
  "custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/metadata/logicDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/layouts/Appuntamento/detailEsitoPopup.json"
  "custom/Espo/Custom/Hooks/InvitoAFatturare/InvitoBeforeSave.php"
  "tools/diagnose-appuntamento-save.php"
  "tools/diagnose-duplicate-hooks.php"
  "tools/fix-duplicate-hooks.php"
  "tools/fix-duplicate-hooks.sh"
)

echo "=== Backup ${BACKUP} ==="
mkdir -p "${BACKUP}"

for rel in "${FILES[@]}"; do
  src="${CRM_ROOT}/${rel}"
  if [[ -f "${src}" ]]; then
    mkdir -p "${BACKUP}/$(dirname "${rel}")"
    cp -a "${src}" "${BACKUP}/${rel}"
    echo "BACKUP ${rel}"
  fi
done

echo ""
echo "=== Download fix calendario 500 ==="
for rel in "${FILES[@]}"; do
  dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=${STAMP}" -o "${dest}"
  echo "OK ${rel}"
done

grep -q "implements BeforeSave" "${CRM_ROOT}/custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php"
grep -q "normalizeMultiEnum" "${CRM_ROOT}/custom/Espo/Custom/Hooks/Appuntamento/RequiredDefaults.php"
grep -q "class InvitoBeforeSave" "${CRM_ROOT}/custom/Espo/Custom/Hooks/InvitoAFatturare/InvitoBeforeSave.php"
test ! -f "${CRM_ROOT}/custom/Espo/Custom/Hooks/InvitoAFatturare/BeforeSave.php"
grep -q "resolveAdminUserId" "${CRM_ROOT}/custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php"
grep -q "status') !== 'Held'" "${CRM_ROOT}/custom/Espo/Custom/Hooks/Appuntamento/ProvvigioneForecast.php"
grep -q "videoCallTelefonico" "${CRM_ROOT}/custom/Espo/Custom/Services/Appuntamento.php"
grep -q "findDuplicateProspect" "${CRM_ROOT}/custom/Espo/Custom/Services/Prospect.php"

echo ""
echo "=== Quarantena hook duplicati ==="
php "${CRM_ROOT}/tools/fix-duplicate-hooks.php" || true

echo ""
echo "=== Diagnostica salvataggio ==="
cd "${CRM_ROOT}"
php tools/diagnose-appuntamento-save.php || true

echo ""
echo "=== Pulizia cache ==="
php clear_cache.php 2>/dev/null || true
rm -rf data/cache/* 2>/dev/null || true
php -r "if (function_exists('opcache_reset')) { opcache_reset(); echo \"opcache_reset OK\n\"; }" 2>/dev/null || true

echo ""
echo "Fatto. Ricarica calendario (Ctrl+Shift+R) e riprova Salva."
echo "Se ancora errore: tail -80 data/logs/espo-\$(date +%Y-%m-%d).log | grep ERROR"
