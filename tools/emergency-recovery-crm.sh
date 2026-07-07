#!/usr/bin/env bash
# Recovery CRM: fix controller Appuntamento + CallStandardTesto (Espo 10).
#
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-kpi-lordi-pianificati-9999/tools/emergency-recovery-crm.sh" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/fix-kpi-lordi-pianificati-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

echo "=== Emergency recovery CRM ==="
cd "${CRM_ROOT}"

fetch() {
  curl -fsSL "${BASE}/$1" -o "$1"
  echo "OK $1"
}

fetch custom/Espo/Custom/Services/CallStandardTesto.php
fetch custom/Espo/Custom/Controllers/CallStandardTesto.php
fetch custom/Espo/Custom/Controllers/Appuntamento.php
fetch custom/Espo/Custom/Hooks/Call/PersistStandardTesto.php

php clear_cache.php && php rebuild.php

echo "=== Fatto — ricarica CRM (Ctrl+Shift+R) ==="
