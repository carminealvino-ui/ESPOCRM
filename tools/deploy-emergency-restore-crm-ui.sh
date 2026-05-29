#!/usr/bin/env bash
# Ripristino CRM pagina bianca: clientDefs minimale, cache, niente calculation-handler.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/quote-prezzi-iva-inclusa-9999/tools/deploy-emergency-restore-crm-ui.sh?t=$(date +%s)" -o /tmp/restore-crm.sh
#   bash /tmp/restore-crm.sh
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/quote-prezzi-iva-inclusa-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}" || exit 1

echo "=== Ripristino emergenza CRM ==="

curl -fsSL "${BASE}/custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json?t=$(date +%s)" \
  -o "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json"
echo "OK clientDefs/Quote.json (solo controller)"

ENTITY_DEFS="${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json"
if [[ -f "${ENTITY_DEFS}" ]]; then
  php -r "
    \$f = '${ENTITY_DEFS}';
    \$j = json_decode(file_get_contents(\$f), true);
    if (is_array(\$j) && isset(\$j['fields']['itemList']['view'])) {
      unset(\$j['fields']['itemList']['view']);
      file_put_contents(\$f, json_encode(\$j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
      echo 'OK entityDefs Quote: rimossa view itemList custom' . PHP_EOL;
    }
  "
fi

rm -f "${CRM_ROOT}/client/custom/src/handlers/quote/calculation-handler.js"
rm -f "${CRM_ROOT}/custom/Espo/Custom/Resources/layouts/Quote/edit.json"
rm -f "${CRM_ROOT}/custom/Espo/Custom/Hooks/Quote/SyncContractPricingAfterSave.php"

if [[ -f "${CRM_ROOT}/custom/Espo/Custom/Hooks/Quote/SyncContractPricingAfterSave.php.disabled" ]]; then
  :
elif [[ -f "${BASE}/custom/Espo/Custom/Hooks/Quote/SyncContractPricingAfterSave.php.disabled" ]]; then
  curl -fsSL "${BASE}/custom/Espo/Custom/Hooks/Quote/SyncContractPricingAfterSave.php.disabled?t=$(date +%s)" \
    -o "${CRM_ROOT}/custom/Espo/Custom/Hooks/Quote/SyncContractPricingAfterSave.php.disabled" 2>/dev/null || true
fi

# Rimuovi calculationHandler da clientDefs se ancora presente (deploy vecchio)
if [[ -f "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json" ]]; then
  php -r "
    \$f = '${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json';
    \$j = json_decode(file_get_contents(\$f), true);
    if (!is_array(\$j)) { exit(0); }
    unset(\$j['calculationHandler']);
    file_put_contents(\$f, json_encode(\$j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
  "
fi

rm -rf "${CRM_ROOT}/data/cache"/*
rm -rf "${CRM_ROOT}/data/tmp"/* 2>/dev/null || true

php command.php rebuild
php command.php clearCache 2>/dev/null || true

echo ""
echo "Fatto. Apri il CRM con Ctrl+F5 (cache browser)."
echo "Se ancora bianco, controlla: tail -30 data/logs/espo-*.log"
echo "Formula Quote NON modificata da questo script."
