#!/usr/bin/env bash
# Applica TUTTO l'elenco Appuntamenti (layout + CSS + list.js) con backup e verifica.
# Se "ancora uguale", di solito mancava list.js o clientDefs sul server.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL ".../tools/applica-elenco-appuntamenti-produzione.sh?t=$(date +%s)" | bash
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/appuntamento-produzione-fruibile-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}" || exit 1

mkdir -p tools
curl -fsSL "${BASE}/tools/backup-produzione.sh?t=$(date +%s)" -o tools/backup-produzione.sh
chmod +x tools/backup-produzione.sh

echo "=== 1/4 Backup ==="
STAMP="$(bash tools/backup-produzione.sh elenco-appuntamenti | tail -1)"
echo "Backup: ${STAMP}"

FILES=(
  "custom/Espo/Custom/Resources/layouts/Appuntamento/list.json"
  "custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/metadata/app/client.json"
  "client/custom/src/views/appuntamento/record/list.js"
  "client/custom/css/custom-ui.css"
  "client/custom/css/appuntamento-list.css"
)

echo ""
echo "=== 2/4 Download file ==="
for rel in "${FILES[@]}"; do
  dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=$(date +%s)" -o "${dest}"
  echo "OK ${rel}"
done

echo ""
echo "=== 3/4 Rebuild ==="
php command.php rebuild
php command.php clearCache 2>/dev/null || true
rm -rf data/cache/*

echo ""
echo "=== 4/4 Verifica (deve essere OK) ==="
FAIL=0

if grep -q 'custom:views/appuntamento/record/list' "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json"; then
  echo "OK clientDefs → list view custom"
else
  echo "ERRORE: clientDefs NON punta a custom:views/appuntamento/record/list"
  FAIL=1
fi

if [[ -f "${CRM_ROOT}/client/custom/src/views/appuntamento/record/list.js" ]]; then
  echo "OK list.js presente"
else
  echo "ERRORE: list.js assente"
  FAIL=1
fi

if grep -q 'appuntamento-list.css' "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/app/client.json"; then
  echo "OK app/client.json carica appuntamento-list.css"
else
  echo "ERRORE: appuntamento-list.css non in client.json"
  FAIL=1
fi

if grep -q '"esito"' "${CRM_ROOT}/custom/Espo/Custom/Resources/layouts/Appuntamento/list.json"; then
  echo "OK list.json contiene colonna esito"
else
  echo "ERRORE: list.json vecchio (manca esito?)"
  FAIL=1
fi

echo ""
if [[ "${FAIL}" -gt 0 ]]; then
  echo "Correggere gli errori sopra, poi Ctrl+F5."
  exit 1
fi

echo "Fatto. Ctrl+F5 (svuota cache browser)."
echo "In DevTools → Elements, elenco deve avere class list-appuntamento-fullwidth"
echo "e data-entity-type=Appuntamento sulla list view."
