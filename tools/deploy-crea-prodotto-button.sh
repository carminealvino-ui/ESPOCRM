#!/usr/bin/env bash
# Pulsante «Crea prodotto» su Contratto — solo clientDefs + handler (no duplicati DOM)
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/provvigioni-manuali-fase-a-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"
CLIENT_JSON="${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/app/client.json"
LEGACY_SCRIPT="client/custom/src/custom-product-button.js"

cd "${CRM_ROOT}" || exit 1

fetch() {
  local rel="$1"
  local dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=$(date +%s)" -o "${dest}"
  echo "OK ${rel}"
}

echo "=== Deploy Crea prodotto ==="

fetch "custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json"
fetch "client/custom/src/action-handlers/quote/crea-prodotto.js"
fetch "client/custom/src/views/quote/fields/item-list.js"
fetch "client/custom/src/views/modals/select-product-for-quote.js"

# Rimuovi script DOM legacy (causava doppio pulsante in testata)
rm -f "${CRM_ROOT}/${LEGACY_SCRIPT}"
if [[ -f "${CLIENT_JSON}" ]]; then
  php -r "
    \$f = '${CLIENT_JSON}';
    \$legacy = '${LEGACY_SCRIPT}';
    \$j = json_decode(file_get_contents(\$f), true);
    foreach (['scriptList', 'developerModeScriptList'] as \$key) {
      if (!isset(\$j[\$key]) || !is_array(\$j[\$key])) {
        continue;
      }
      \$j[\$key] = array_values(array_filter(\$j[\$key], function (\$v) use (\$legacy) {
        return \$v !== \$legacy;
      }));
    }
    file_put_contents(\$f, json_encode(\$j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
  "
  echo "OK rimosso ${LEGACY_SCRIPT} da app/client.json (se presente)"
fi

if [[ -f "${CRM_ROOT}/client/custom/src/action-handlers/quote/crea-prodotto.js" ]]; then
  echo "Verifica: handler crea-prodotto installato"
else
  echo "ERRORE: handler mancante" >&2
  exit 1
fi

php command.php rebuild
rm -rf data/cache/*
echo ""
echo "Fatto. Un solo pulsante «Crea prodotto» in testata (metadata). Cache browser: Ctrl+Shift+R"
