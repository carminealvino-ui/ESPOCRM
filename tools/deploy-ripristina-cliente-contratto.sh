#!/usr/bin/env bash
set -euo pipefail

SHA="${1:-$(git rev-parse HEAD)}"
ROOT="${2:-$HOME/public_html/crm/mec-group}"

echo "Deploy ripristina-cliente-contratto SHA=${SHA} -> ${ROOT}"

curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${SHA}/custom/Espo/Custom/Services/ContrattoClienteFromOpportunitaResolver.php" \
  -o "${ROOT}/custom/Espo/Custom/Services/ContrattoClienteFromOpportunitaResolver.php"

curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${SHA}/tools/ripristina-cliente-contratto-da-opportunita.php" \
  -o "${ROOT}/tools/ripristina-cliente-contratto-da-opportunita.php"

cd "${ROOT}"
php clear_cache.php

echo "OK. Esegui bonifica:"
echo "  php tools/ripristina-cliente-contratto-da-opportunita.php --fix-all --dry-run"
echo "  php tools/ripristina-cliente-contratto-da-opportunita.php --fix-all"
