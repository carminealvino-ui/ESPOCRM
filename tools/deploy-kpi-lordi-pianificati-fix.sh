#!/usr/bin/env bash
# KPI: esclude appuntamenti Pianificato (Planned) dai lordi + etichette Opportunità brevi.
#
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-kpi-lordi-pianificati-9999/tools/deploy-kpi-lordi-pianificati-fix.sh" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/fix-kpi-lordi-pianificati-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

echo "=== Deploy fix KPI lordi (no Pianificato) + etichette Opportunità ==="
cd "${CRM_ROOT}"

fetch() {
  curl -fsSL "${BASE}/$1" -o "$1"
  echo "OK $1"
}

fetch custom/Espo/Custom/Services/CrmKpi/CrmKpiService.php
fetch client/custom/src/views/dashlets/crm-kpi.js

php clear_cache.php && php rebuild.php

echo "=== Completato — Ctrl+Shift+R sulla dashboard KPI ==="
