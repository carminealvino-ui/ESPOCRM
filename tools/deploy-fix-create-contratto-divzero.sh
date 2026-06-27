#!/usr/bin/env bash
# Hotfix: DivisionByZero su Crea Contratto (formula Quote + createEntity).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/quote-stati-condizionale-9999/tools/deploy-fix-create-contratto-divzero.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/quote-stati-condizionale-9999"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)

FILES=(
  "custom/Espo/Custom/Actions/Opportunity/CreateContratto.php"
  "custom/Espo/Custom/Resources/metadata/formula/Quote.json"
)

echo "=== Hotfix Crea Contratto (division by zero) ==="

for rel in "${FILES[@]}"; do
  dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=${STAMP}" -o "${dest}"
  echo "OK ${rel}"
  if [[ "${rel}" == *.json ]]; then
    php -r "json_decode(file_get_contents('${dest}')); if (json_last_error()) { fwrite(STDERR, json_last_error_msg()); exit(1); }"
    echo "JSON OK ${rel}"
  fi
done

echo ""
(cd "${CRM_ROOT}" && php clear_cache.php && php rebuild.php)
echo "Fatto. Riprova Crea Contratto su opportunità Closed Won."
