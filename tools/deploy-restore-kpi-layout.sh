#!/usr/bin/env bash
# Ripristina layout dashlet KPI (client.json + crm-kpi-dashlet.css da branch KPI v2).
# NON usare deploy-create-contratto-fix.sh per il KPI: sovrascrive client.json.
#
# Uso:
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-contratto-stato-provvigioni-9999/tools/deploy-restore-kpi-layout.sh" | bash
#
# Deploy completo KPI v2 (template, JS, service):
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/crm-kpi-dashlet-v2-9999/tools/deploy-crm-kpi-v2-hotfix.sh" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/crm-kpi-dashlet-v2-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}"

fetch() {
  local path="$1"
  mkdir -p "$(dirname "$path")"
  curl -fL --retry 8 --retry-all-errors --retry-delay 2 --retry-max-time 120 \
    "${BASE}/${path}" -o "${path}"
  echo "OK ${path}"
}

fetch custom/Espo/Custom/Resources/metadata/app/client.json
fetch client/custom/css/crm-kpi-dashlet.css

php clear_cache.php
php rebuild.php

echo "=== Fatto: layout KPI ripristinato (branch ${BRANCH}) ==="
echo "Se il layout non torna corretto, esegui anche deploy-crm-kpi-v2-hotfix.sh dalla stessa branch."
