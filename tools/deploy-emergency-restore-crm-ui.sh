#!/usr/bin/env bash
# Ripristino vista Contratto: NON sovrascrive layout né entityDefs da GitHub.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL ".../cursor/quote-prezzi-iva-inclusa-9999/tools/deploy-emergency-restore-crm-ui.sh?t=$(date +%s)" -o /tmp/restore-crm.sh
#   bash /tmp/restore-crm.sh
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/quote-prezzi-iva-inclusa-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}" || exit 1

bash "${CRM_ROOT}/tools/backup-quote-layouts.sh" 2>/dev/null || true

echo "=== Ripristino vista Contratto (senza toccare layout file) ==="

curl -fsSL "${BASE}/custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json?t=$(date +%s)" \
  -o "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json"

curl -fsSL "${BASE}/client/custom/src/handlers/quote/crea-prodotto-articoli.js?t=$(date +%s)" \
  -o "${CRM_ROOT}/client/custom/src/handlers/quote/crea-prodotto-articoli.js"

rm -f "${CRM_ROOT}/client/custom/src/handlers/quote/calculation-handler.js"
rm -f "${CRM_ROOT}/custom/Espo/Custom/Hooks/Quote/SyncContractPricingAfterSave.php"
rm -f "${CRM_ROOT}/custom/Espo/Custom/Resources/layouts/Quote/edit.json"

QUOTE_DEFS="${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json"
php -r "
  \$f = '${QUOTE_DEFS}';
  \$j = json_decode(file_get_contents(\$f), true);
  if (!is_array(\$j)) { exit(1); }
  unset(\$j['calculationHandler']);
  file_put_contents(\$f, json_encode(\$j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
"

ENTITY_DEFS="${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json"
if [[ -f "${ENTITY_DEFS}" ]]; then
  php -r "
    \$f = '${ENTITY_DEFS}';
    \$j = json_decode(file_get_contents(\$f), true);
    if (is_array(\$j) && isset(\$j['fields']['itemList']['view'])) {
      unset(\$j['fields']['itemList']['view']);
      if (isset(\$j['fields']['itemList']) && \$j['fields']['itemList'] === []) {
        unset(\$j['fields']['itemList']);
      }
      file_put_contents(\$f, json_encode(\$j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
      echo 'OK: rimossa solo view itemList custom (file layout intatti)' . PHP_EOL;
    }
  "
fi

rm -rf "${CRM_ROOT}/data/cache"/*
php command.php rebuild
php command.php clearCache 2>/dev/null || true

echo ""
echo "Layout in custom/Espo/Custom/Resources/layouts/Quote/ NON scaricati da GitHub."
echo "Se il layout è stato perso: Admin > Layout Manager > Quote > ripristina o usa backup in custom/backup-layouts/"
