#!/usr/bin/env bash
# PR layout provvigioni — rimuove campi inutili da dettaglio/subpanel.
# NON modifica il calcolo (→ deploy-provvigioni-imponibile-netto.sh PR #99).
#
# Uso:
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/provvigione-layout-pulizia-9999/tools/deploy-provvigione-layout-pulizia.sh?t=$(date +%s)" | bash
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="cursor/provvigione-layout-pulizia-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"
TS="$(date +%s)"

cd "${CRM_ROOT}" || exit 1

fetch() {
  local path="$1"
  mkdir -p "$(dirname "$path")"
  curl -fL --retry 8 --retry-all-errors --retry-delay 2 --retry-max-time 120 \
    "${BASE}/${path}?t=${TS}" -o "${path}"
  echo "OK ${path}"
}

FILES=(
  custom/Espo/Custom/Resources/metadata/entityDefs/Provvigione.json
  custom/Espo/Custom/Resources/metadata/scopes/InvitoAFatturare.json
  custom/Espo/Custom/Resources/metadata/entityDefs/InvitoAFatturare.json
  custom/Espo/Custom/Resources/metadata/recordDefs/InvitoAFatturare.json
  custom/Espo/Custom/Resources/i18n/it_IT/InvitoAFatturare.json
  custom/Espo/Custom/Resources/layouts/Provvigione/detail.json
  custom/Espo/Custom/Resources/layouts/Provvigione/edit.json
  custom/Espo/Custom/Resources/layouts/Quote/relationships/provvigioni.json
  custom/Espo/Custom/Resources/metadata/clientDefs/Provvigione.json
  custom/Espo/Custom/Resources/i18n/it_IT/Provvigione.json
  client/custom/src/views/provvigione/record/detail.js
  client/custom/src/views/provvigione/record/edit.js
)

for rel in "${FILES[@]}"; do
  fetch "${rel}"
done

rm -f custom/Espo/Custom/Hooks/Provvigione/BeforeSaveLegacy.php

mkdir -p custom/Espo/Custom/Resources/metadata/clientDefs
cat > custom/Espo/Custom/Resources/metadata/clientDefs/InvitoAFatturare.json <<'EOF'
{
    "controller": "controllers/record"
}
EOF
echo "OK custom/Espo/Custom/Resources/metadata/clientDefs/InvitoAFatturare.json (minimal)"

php clear_cache.php
php rebuild.php

echo ""
echo "=== Fatto: layout Provvigione pulito ==="
echo "Rimossi da UI: importo previsto, stato pagamento, date liquidazione, regola duplicata"
echo "InvitoAFatturare registrato in metadata (fix 500 GET /Provvigione)"
echo "Per correggere importo base/consolidato: deploy PR #99 provvigioni + ricalcolo batch"
