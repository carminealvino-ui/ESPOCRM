#!/usr/bin/env bash
# Ripristina layout Contratto senza pannello Provvigioni (calcolo) e sub-pannello provvigioni.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-quote-layout-ripristino-9999/tools/deploy-fix-quote-layout-ripristino.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/fix-quote-layout-ripristino-9999"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)

FILES=(
  "custom/Espo/Custom/Resources/layouts/Quote/detail.json"
  "custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json"
  "custom/Espo/Custom/Actions/Opportunity/CreateContratto.php"
  "custom/Espo/Custom/Hooks/Quote/BeforeSave.php"
)

echo "=== Ripristino layout Contratto (no provvigioni UI) ==="
echo "Branch: ${BRANCH}"
echo "CRM:    ${CRM_ROOT}"
echo ""

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

# Rimuove hook auto-provvigioni consolidata se presente in produzione
PROVV_HOOK="${CRM_ROOT}/custom/Espo/Custom/Hooks/Quote/ProvvigioneConsolidata.php"
if [[ -f "${PROVV_HOOK}" ]]; then
  rm -f "${PROVV_HOOK}"
  echo "RIMOSSO ${PROVV_HOOK}"
fi

echo ""
(cd "${CRM_ROOT}" && php clear_cache.php && php rebuild.php)
echo ""
echo "Fatto. Ctrl+F5 nel browser."
echo "Verifica: nessun pannello 'Provvigioni (calcolo)' sul Contratto."
