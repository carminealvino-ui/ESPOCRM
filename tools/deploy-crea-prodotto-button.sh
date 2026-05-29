#!/usr/bin/env bash
# «Crea prodotto» a sinistra del + nella tabella articoli. NON installa calculationHandler.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL ".../cursor/quote-prezzi-iva-inclusa-9999/tools/deploy-crea-prodotto-button.sh?t=$(date +%s)" -o /tmp/deploy-btn.sh
#   bash /tmp/deploy-btn.sh
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/quote-prezzi-iva-inclusa-9999}"
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

echo "=== Deploy Crea prodotto (senza calculation-handler) ==="

fetch "custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json"
fetch "custom/Espo/Custom/Resources/metadata/formula/Quote.json"
fetch "client/custom/src/handlers/quote/crea-prodotto-articoli.js"
fetch "client/custom/src/views/modals/select-product-for-quote.js"

# entityDefs senza view itemList custom (evita pagina bianca su Contratto)
curl -fsSL "${BASE}/custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json?t=$(date +%s)" \
  -o "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json"

rm -f "${CRM_ROOT}/${LEGACY_SCRIPT}"
rm -f "${CRM_ROOT}/client/custom/src/handlers/quote/calculation-handler.js"
rm -f "${CRM_ROOT}/client/custom/src/handlers/quote/articoli-crea-prodotto-setup.js"

QUOTE_DEFS="${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json"
php -r "
  \$f = '${QUOTE_DEFS}';
  \$j = json_decode(file_get_contents(\$f), true);
  if (!is_array(\$j)) { exit(1); }
  unset(\$j['calculationHandler']);
  file_put_contents(\$f, json_encode(\$j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
"

mkdir -p "${CRM_ROOT}/tools"
curl -fsSL "${BASE}/tools/dedupe-quote-crea-prodotto.php?t=$(date +%s)" -o "${CRM_ROOT}/tools/dedupe-quote-crea-prodotto.php"
CRM_ROOT="${CRM_ROOT}" php "${CRM_ROOT}/tools/dedupe-quote-crea-prodotto.php" 2>/dev/null || true

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
fi

php command.php rebuild
rm -rf data/cache/*
echo ""
echo "Fatto: Crea prodotto + formula Quote 1.5.1 (nome senza grandTotalAmount)."
echo "Ctrl+Shift+R sul browser."
