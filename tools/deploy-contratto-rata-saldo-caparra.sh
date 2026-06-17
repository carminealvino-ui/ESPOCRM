#!/usr/bin/env bash
# Contratto: caparra sempre visibile, rata prestito, importo saldo auto.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/contratto-rata-saldo-caparra-9999/tools/deploy-contratto-rata-saldo-caparra.sh?t=$(date +%s)" -o /tmp/deploy-contratto-rata-saldo.sh
#   bash /tmp/deploy-contratto-rata-saldo.sh
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/contratto-rata-saldo-caparra-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}" || exit 1

bash "${CRM_ROOT}/tools/backup-quote-layouts.sh" 2>/dev/null || true

fetch() {
  local rel="$1"
  local dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=$(date +%s)" -o "${dest}"
  echo "OK ${rel}"
}

echo "=== Deploy Contratto: caparra, rata prestito, importo saldo ==="

for rel in \
  "custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json" \
  "custom/Espo/Custom/Resources/metadata/logicDefs/Quote.json" \
  "custom/Espo/Custom/Resources/metadata/formula/Quote.json" \
  "custom/Espo/Custom/Resources/layouts/Quote/detail.json" \
  "custom/Espo/Custom/Resources/i18n/it_IT/Quote.json" \
  "custom/Espo/Custom/Resources/i18n/en_US/Quote.json"
do
  fetch "${rel}"
done

php command.php rebuild
rm -rf data/cache/*
echo ""
echo "Fatto. Ctrl+Shift+R sul browser."
