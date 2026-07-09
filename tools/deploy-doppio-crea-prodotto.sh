#!/usr/bin/env bash
# PR #9 — Un solo pulsante «Crea prodotto» in Articoli contratto.
# Rimuove custom-product-button.js (pulsante cubo duplicato).
#
# Ordine: deployare DOPO PR #90 e PR #99.
#
# Uso:
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-doppio-crea-prodotto-9999/tools/deploy-doppio-crea-prodotto.sh?t=$(date +%s)" | bash
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"

cd "${CRM_ROOT}" || exit 1

echo "=== PR #9: rimuove doppio pulsante Crea prodotto ==="

rm -f client/custom/src/custom-product-button.js
rm -f custom/Espo/Custom/Resources/client/custom/src/custom-product-button.js

CJ="custom/Espo/Custom/Resources/metadata/app/client.json"
if [[ -f "${CJ}" ]] && grep -q 'custom-product-button' "${CJ}" 2>/dev/null; then
  php -r '
    $p = $argv[1];
    $j = json_decode(file_get_contents($p), true);
    if (!is_array($j)) { fwrite(STDERR, "client.json non valido\n"); exit(1); }
    foreach (["scriptList"] as $k) {
      if (!isset($j[$k]) || !is_array($j[$k])) continue;
      $j[$k] = array_values(array_filter($j[$k], fn($v) => $v !== "client/custom/src/custom-product-button.js"));
    }
    file_put_contents($p, json_encode($j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
  ' "${CJ}"
  echo "OK: rimosso custom-product-button da client.json"
else
  echo "OK: client.json già senza custom-product-button"
fi

php clear_cache.php

echo ""
echo "=== Fatto PR #9 ==="
echo "Atteso: un solo «Crea prodotto» nel menu … della sezione Articoli"
