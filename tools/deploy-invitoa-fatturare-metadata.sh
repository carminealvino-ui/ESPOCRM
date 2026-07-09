#!/usr/bin/env bash
# Hotfix: registra entity InvitoAFatturare in metadata (fix 500 GET /Provvigione).
# Necessario dopo deploy layout Provvigione (link invitoAFatturare).
#
# Uso:
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/provvigione-layout-pulizia-9999/tools/deploy-invitoa-fatturare-metadata.sh?t=$(date +%s)" | bash
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

echo "=== Registra InvitoAFatturare in metadata ==="

for rel in \
  custom/Espo/Custom/Resources/metadata/scopes/InvitoAFatturare.json \
  custom/Espo/Custom/Resources/metadata/entityDefs/InvitoAFatturare.json \
  custom/Espo/Custom/Resources/metadata/recordDefs/InvitoAFatturare.json \
  custom/Espo/Custom/Resources/i18n/it_IT/InvitoAFatturare.json
do
  fetch "${rel}"
done

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
echo "=== Fatto — riprova ad aprire la Provvigione ==="
