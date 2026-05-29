#!/usr/bin/env bash
# Fix schermata bianca Duplica Appuntamento: ricompila client CRM (meeting/record/edit.js).
#
# Errore tipico in console:
#   404 .../client/lib/transpiled/modules/crm/src/views/meeting/record/edit.js
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/main/tools/deploy-fix-appuntamento-duplica.sh?t=$(date +%s)" | bash
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-main}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}" || exit 1

echo "=== Fix Appuntamento / Duplica (rebuild client CRM) ==="

mkdir -p tools
curl -fsSL "${BASE}/custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json?t=$(date +%s)" \
  -o "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json"
echo "OK clientDefs Appuntamento"

for rel in \
  "client/custom/src/views/appuntamento/record/edit.js" \
  "client/custom/src/views/appuntamento/record/edit-small.js" \
  "client/custom/src/views/appuntamento/record/detail.js" \
  "client/custom/src/views/appuntamento/modals/detail.js"
do
  dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=$(date +%s)" -o "${dest}" 2>/dev/null && echo "OK ${rel}" || true
done

MEETING_EDIT="${CRM_ROOT}/client/lib/transpiled/modules/crm/src/views/meeting/record/edit.js"
CRM_SRC="${CRM_ROOT}/client/modules/crm/src/views/meeting/record/edit.js"

php command.php rebuild
php command.php clearCache 2>/dev/null || true
rm -rf data/cache/*

echo ""
if [[ -f "${MEETING_EDIT}" ]]; then
  echo "OK: trovato ${MEETING_EDIT}"
elif [[ -f "${CRM_SRC}" ]]; then
  echo "ATTENZIONE: sorgente CRM presente ma transpiled mancante — riprovare rebuild o permessi su client/lib/"
else
  echo "ERRORE: modulo CRM non trovato. Verificare estensione EspoCRM CRM installata (pacchetto meeting)."
  exit 1
fi

echo "Fatto. Ricarica CRM con Ctrl+F5 e riprova Duplica su Appuntamento."
