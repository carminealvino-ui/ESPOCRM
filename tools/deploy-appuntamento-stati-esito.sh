#!/usr/bin/env bash
# Appuntamento: stati/sottostato/esito + esclusione Gestito/Rifissato dal monitoraggio.
# Backup obbligatorio in backup_dev/_sessions.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/appuntamento-stati-esito-9999/tools/deploy-appuntamento-stati-esito.sh?t=$(date +%s)" | bash
#
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/appuntamento-stati-esito-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP="$(date +%Y%m%d-%H%M%S)"

FILES=(
  "custom/Espo/Custom/Services/AppuntamentoStatiRules.php"
  "custom/Espo/Custom/Hooks/Appuntamento/SyncStatiEsito.php"
  "custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/metadata/hooks/Appuntamento.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Appuntamento.json"
  "custom/Espo/Custom/Services/CrmKpi/CrmKpiService.php"
  "custom/Espo/Custom/Tools/CrmKpi/Alerts.php"
  "client/custom/src/helpers/appuntamento-sottostato-map.js"
  "client/custom/src/views/fields/appuntamento-sottostato.js"
  "client/custom/src/views/fields/appuntamento-esito.js"
  "custom/Espo/Custom/Resources/layouts/Appuntamento/detailEsitoPopup.json"
)

cd "${CRM_ROOT}"

echo "=== Appuntamento stati/esito — BRANCH=${BRANCH} ==="

echo ""
echo "=== PASSO 0 — Backup ==="
SESSION="backup_dev/_sessions/${STAMP}_appuntamento-stati-esito"
mkdir -p "${SESSION}"

for rel in "${FILES[@]}"; do
  src="${CRM_ROOT}/${rel}"
  if [[ -f "${src}" ]]; then
    mkdir -p "${SESSION}/$(dirname "${rel}")"
    cp -a "${src}" "${SESSION}/${rel}"
    echo "BACKUP ${rel}"
  else
    echo "SKIP (nuovo): ${rel}"
  fi
done
echo "stamp=${STAMP}" > "${SESSION}/log.txt"
echo "Sessione: ${SESSION}"
ls -la backup_dev/_sessions/ | tail -5

echo ""
echo "=== PASSO 1 — Download ==="
for rel in "${FILES[@]}"; do
  dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=${STAMP}" -o "${dest}"
  echo "OK ${rel}"
done

echo ""
echo "=== Clear cache + rebuild ==="
php clear_cache.php
php rebuild.php

echo ""
echo "=== FATTO ==="
echo "Verifica UI: cambia Esito su un Appuntamento e controlla Stato/Sottostato."
echo "Gestito e Rifissato non entrano nel monitoraggio KPI."
