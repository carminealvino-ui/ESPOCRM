#!/usr/bin/env bash
# Hotfix KPI 500: Appuntamento::getContainer() non esiste in Espo 10.
#
# Uso:
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-contratto-stato-provvigioni-9999/tools/deploy-kpi-fix-500.sh" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/fix-contratto-stato-provvigioni-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}"

fetch() {
  local path="$1"
  mkdir -p "$(dirname "$path")"
  curl -fL --retry 8 --retry-all-errors --retry-delay 2 --retry-max-time 120 \
    "${BASE}/${path}" -o "${path}"
  echo "OK ${path}"
}

fetch custom/Espo/Custom/Controllers/Appuntamento.php
fetch custom/Espo/Custom/Controllers/CrmKpi.php

php clear_cache.php

echo "=== Fatto: KPI fix 500 (controller Appuntamento Espo 10) ==="
