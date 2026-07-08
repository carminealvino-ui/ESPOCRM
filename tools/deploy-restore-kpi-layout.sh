#!/usr/bin/env bash
# Ripristina layout dashlet KPI (crm-kpi-dashlet.css in client.json).
#
# Uso:
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-calendario-appuntamento-9999/tools/deploy-restore-kpi-layout.sh" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/fix-calendario-appuntamento-9999"
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

echo "=== Fatto: layout KPI ripristinato (crm-kpi-dashlet.css) ==="
