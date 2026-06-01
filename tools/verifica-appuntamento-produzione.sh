#!/usr/bin/env bash
# Diagnostica rapida Appuntamento in produzione (solo lettura).
#
#   cd ~/public_html/crm/mec-group
#   bash tools/verifica-appuntamento-produzione.sh
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
cd "${CRM_ROOT}" || exit 1

echo "=== Appuntamento — verifica produzione ==="
echo "CRM_ROOT: ${CRM_ROOT}"
echo ""

CLIENT_DEFS="custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json"
ENTITY_DEFS="custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json"

fail=0

check_no() {
  local label="$1"
  local file="$2"
  local pattern="$3"
  if [[ -f "${file}" ]] && grep -qE "${pattern}" "${file}" 2>/dev/null; then
    echo "FAIL ${label}: trovato '${pattern}' in ${file}"
    fail=$((fail + 1))
  else
    echo "OK   ${label}"
  fi
}

check_yes() {
  local label="$1"
  local file="$2"
  local pattern="$3"
  if [[ -f "${file}" ]] && grep -qE "${pattern}" "${file}" 2>/dev/null; then
    echo "OK   ${label}"
  else
    echo "FAIL ${label}: atteso '${pattern}' in ${file}"
    fail=$((fail + 1))
  fi
}

check_no "clientDefs senza crm:meeting" "${CLIENT_DEFS}" 'crm:views/meeting'
check_yes "clientDefs edit configurato" "${CLIENT_DEFS}" '"edit":\s*"(views/record/edit|custom:views/appuntamento/record/edit)"'
check_no "entityDefs date senza crm meeting fields" "${ENTITY_DEFS}" 'crm:views/meeting/fields'
check_no "entityDefs reminders senza view json-array implicita" "${ENTITY_DEFS}" '"reminders"[^}]*crm:views/meeting'
DETAIL_LAYOUT="custom/Espo/Custom/Resources/layouts/Appuntamento/detail.json"
if [[ -f "${DETAIL_LAYOUT}" ]] && grep -q '"reminders"' "${DETAIL_LAYOUT}"; then
  echo "FAIL layout detail contiene ancora reminders (404 json-array.js)"
  fail=$((fail + 1))
else
  echo "OK   layout detail senza reminders"
fi
if [[ -f "client/custom/src/views/fields/reminders-disabled.js" ]]; then
  echo "OK   reminders-disabled.js presente"
else
  echo "WARN reminders-disabled.js assente"
fi

for f in \
  "client/custom/src/views/appuntamento/record/edit.js" \
  "client/custom/src/views/appuntamento/record/detail.js" \
  "custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php"; do
  if [[ -f "${f}" ]]; then
    echo "OK   file presente: ${f}"
  else
    echo "WARN file assente: ${f}"
  fi
done

if [[ -f "client/custom/src/views/appuntamento/modals/detail.js" ]]; then
  echo "FAIL modals/detail.js legacy presente (dipende da crm:meeting) — rimuovere"
  fail=$((fail + 1))
else
  echo "OK   nessun modals/detail.js legacy"
fi

if [[ -d "client/lib/transpiled/modules/crm" ]]; then
  echo "OK   modulo client CRM transpiled presente (calendario meeting)"
else
  echo "WARN client/lib/transpiled/modules/crm assente — Crea/Duplica OK se clientDefs corretti; calendario CRM può fallire"
fi

echo ""
if [[ "${fail}" -gt 0 ]]; then
  echo "Risultato: ${fail} problema/i. Eseguire:"
  echo "  curl -fsSL 'https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/main/tools/deploy-appuntamento-produzione.sh?t=\$(date +%s)' | bash"
  exit 1
fi
echo "Risultato: configurazione client/metadata coerente per Crea/Duplica."
echo "Se il browser mostra ancora bianco: Ctrl+F5 o cache svuotata."
