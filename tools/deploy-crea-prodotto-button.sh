#!/usr/bin/env bash
# Pulsante «Crea prodotto» su Contratto — metadata + client/custom/src/
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/provvigioni-manuali-fase-a-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"
CLIENT_JSON="${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/app/client.json"
SCRIPT_ENTRY="client/custom/src/custom-product-button.js"

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
fetch "client/custom/src/custom-product-button.js"
fetch "client/custom/src/views/quote/fields/item-list.js"
fetch "client/custom/src/views/modals/select-product-for-quote.js"

# Registra script fallback in app/client.json (scriptList + developerMode)
if [[ -f "${CLIENT_JSON}" ]]; then
  php -r "
    \$f = '${CLIENT_JSON}';
    \$entry = '${SCRIPT_ENTRY}';
    \$j = json_decode(file_get_contents(\$f), true);
    foreach (['scriptList', 'developerModeScriptList'] as \$key) {
      \$j[\$key] = \$j[\$key] ?? ['__APPEND__'];
      if (!in_array(\$entry, \$j[\$key], true)) {
        \$j[\$key][] = \$entry;
      }
    }
    file_put_contents(\$f, json_encode(\$j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
  "
  echo "OK app/client.json (scriptList + developerModeScriptList)"
else
  echo "ATTENZIONE: ${CLIENT_JSON} non trovato"
fi

if [[ -f "${CRM_ROOT}/${SCRIPT_ENTRY}" ]]; then
  echo "Verifica: $(wc -c < "${CRM_ROOT}/${SCRIPT_ENTRY}") byte — ${SCRIPT_ENTRY}"
else
  echo "ERRORE: file non scritto ${SCRIPT_ENTRY}" >&2
  exit 1
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
echo "Fatto. Su scheda Contratto (vista/edizione): pulsante «Crea prodotto» in testata (metadata Espo)."
echo "Cache browser: Ctrl+Shift+R"
