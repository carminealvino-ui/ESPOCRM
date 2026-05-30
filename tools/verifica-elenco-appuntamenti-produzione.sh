#!/usr/bin/env bash
# Verifica che produzione abbia i file per elenco Appuntamenti (no troncamento).
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
cd "${CRM_ROOT}" || exit 1

echo "=== Verifica elenco Appuntamenti produzione ==="
FAIL=0

check() {
  if [[ "$1" -eq 0 ]]; then
    echo "OK   $2"
  else
    echo "FAIL $2"
    FAIL=$((FAIL + 1))
  fi
}

grep -q 'custom:views/appuntamento/record/list' custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json
check $? "clientDefs list custom"

[[ -f client/custom/src/views/appuntamento/record/list.js ]]
check $? "list.js"

grep -q 'applyNoTruncateColumns' client/custom/src/views/appuntamento/record/list.js 2>/dev/null
check $? "list.js fix no-truncate"

[[ -f client/custom/css/appuntamento-list.css ]]
check $? "appuntamento-list.css"

grep -q 'appuntamento-list.css' custom/Espo/Custom/Resources/metadata/app/client.json
check $? "client.json css"

grep -q '"esito"' custom/Espo/Custom/Resources/layouts/Appuntamento/list.json
check $? "list.json esito"

echo ""
if [[ "${FAIL}" -gt 0 ]]; then
  echo "Eseguire: bash tools/applica-elenco-appuntamenti-produzione.sh"
  exit 1
fi
echo "File OK. Se UI uguale: Ctrl+F5 o cache browser."
