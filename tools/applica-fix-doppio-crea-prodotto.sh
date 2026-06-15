#!/usr/bin/env bash
# Rimuove il doppio pulsante «Crea prodotto» (legacy custom-product-button.js + handler).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-doppio-crea-prodotto-9999/tools/applica-fix-doppio-crea-prodotto.sh?t=$(date +%s)" -o /tmp/fix-doppio.sh
#   bash /tmp/fix-doppio.sh
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/fix-doppio-crea-prodotto-9999}"
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

echo "=== Fix doppio «Crea prodotto» (Quote / Contratto) ==="

fetch "custom/Espo/Custom/Resources/metadata/app/client.json"
fetch "client/custom/src/handlers/quote/crea-prodotto-articoli.js"

rm -f "${CRM_ROOT}/${LEGACY_SCRIPT}"
rm -f "${CRM_ROOT}/custom/Espo/Custom/Resources/client/custom/src/custom-product-button.js"

if [[ -f "${CLIENT_JSON}" ]]; then
  php -r "
    \$f = '${CLIENT_JSON}';
    \$legacy = '${LEGACY_SCRIPT}';
    \$j = json_decode(file_get_contents(\$f), true);
    foreach (['scriptList', 'developerModeScriptList'] as \$key) {
      if (!isset(\$j[\$key]) || !is_array(\$j[\$key])) continue;
      \$j[\$key] = array_values(array_filter(\$j[\$key], function (\$v) use (\$legacy) {
        return \$v !== \$legacy;
      }));
    }
    file_put_contents(\$f, json_encode(\$j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
  "
  echo "OK client.json: rimosso script legacy"
fi

php command.php rebuild
rm -rf data/cache/*

echo ""
echo "Fatto. Ricarica il contratto con Ctrl+Shift+R (un solo pulsante + Crea prodotto)."
