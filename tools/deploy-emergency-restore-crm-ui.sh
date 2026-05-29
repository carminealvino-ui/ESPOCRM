#!/usr/bin/env bash
# Ripristino vista Contratto (pagina bianca): niente item-list custom, no AfterSave, no calculation-handler.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL ".../cursor/quote-prezzi-iva-inclusa-9999/tools/deploy-emergency-restore-crm-ui.sh?t=$(date +%s)" -o /tmp/restore-crm.sh
#   bash /tmp/restore-crm.sh
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/quote-prezzi-iva-inclusa-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}" || exit 1

echo "=== Ripristino vista Contratto (emergenza) ==="

curl -fsSL "${BASE}/custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json?t=$(date +%s)" \
  -o "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json"

curl -fsSL "${BASE}/custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json?t=$(date +%s)" \
  -o "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json"

curl -fsSL "${BASE}/client/custom/src/handlers/quote/crea-prodotto-articoli.js?t=$(date +%s)" \
  -o "${CRM_ROOT}/client/custom/src/handlers/quote/crea-prodotto-articoli.js"

rm -f "${CRM_ROOT}/client/custom/src/handlers/quote/calculation-handler.js"
rm -f "${CRM_ROOT}/custom/Espo/Custom/Hooks/Quote/SyncContractPricingAfterSave.php"
rm -f "${CRM_ROOT}/custom/Espo/Custom/Resources/layouts/Quote/edit.json"

ENTITY_DEFS="${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json"
php -r "
  \$f = '${ENTITY_DEFS}';
  \$j = json_decode(file_get_contents(\$f), true);
  if (is_array(\$j) && isset(\$j['fields']['itemList']['view'])) {
    unset(\$j['fields']['itemList']['view']);
    if (\$j['fields']['itemList'] === []) {
      unset(\$j['fields']['itemList']);
    }
    file_put_contents(\$f, json_encode(\$j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    echo 'OK: rimossa view itemList custom' . PHP_EOL;
  }
"

QUOTE_DEFS="${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json"
php -r "
  \$f = '${QUOTE_DEFS}';
  \$j = json_decode(file_get_contents(\$f), true);
  if (!is_array(\$j)) { exit(1); }
  unset(\$j['calculationHandler']);
  file_put_contents(\$f, json_encode(\$j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
"

rm -rf "${CRM_ROOT}/data/cache"/*
rm -rf "${CRM_ROOT}/data/tmp"/* 2>/dev/null || true

php command.php rebuild
php command.php clearCache 2>/dev/null || true

echo ""
echo "Fatto. Apri: https://crm.mec-group.it (Ctrl+F5)."
echo "Pulsante Crea prodotto: handler articoli (senza item-list custom)."
echo "Prezzi: hook PHP BeforeSave + deploy-prezzi.sh"
