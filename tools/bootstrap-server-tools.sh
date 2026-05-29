#!/usr/bin/env bash
# Scarica tutti gli script tools sul server CRM (cartella tools/ non è nel deploy Espo di default).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/quote-prezzi-iva-inclusa-9999/tools/bootstrap-server-tools.sh?t=$(date +%s)" | bash
#
# Poi:
#   bash tools/backup-quote-layouts.sh
#   bash tools/deploy-contratto-prezzi-curl.sh
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/quote-prezzi-iva-inclusa-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

if [[ ! -f "${CRM_ROOT}/command.php" ]]; then
  echo "ERRORE: eseguire da root CRM (command.php mancante in ${CRM_ROOT})"
  exit 1
fi

cd "${CRM_ROOT}"
mkdir -p tools tools/layouts-samples/Quote

TOOL_FILES=(
  "tools/backup-quote-layouts.sh"
  "tools/restore-quote-layouts.sh"
  "tools/apply-quote-detail-prezzi-sample.sh"
  "tools/deploy-contratto-prezzi-curl.sh"
  "tools/deploy-emergency-restore-crm-ui.sh"
  "tools/deploy-crea-prodotto-button.sh"
  "tools/fix-contratto-importo-minusplus-standalone.php"
  "tools/layouts-samples/Quote/detail-prezzi-minusplus.json"
)

for rel in "${TOOL_FILES[@]}"; do
  dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=$(date +%s)" -o "${dest}"
  if [[ "${rel}" == *.sh ]]; then
    chmod +x "${dest}"
  fi
  echo "OK ${rel}"
done

echo ""
echo "Script in ${CRM_ROOT}/tools/"
echo "  1) bash tools/backup-quote-layouts.sh"
echo "  2) bash tools/deploy-contratto-prezzi-curl.sh"
echo "  3) php tools/fix-contratto-importo-minusplus-standalone.php --name=\"POLTRONI MARZIA\" --importo=4500"
