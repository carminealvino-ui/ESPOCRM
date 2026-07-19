#!/usr/bin/env bash
# Allinea enum + condizioni tra Stato / Stato Contratto / Stato Finanziamento.
# Backup obbligatorio in backup_dev/_sessions.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/quote-stati-recesso-chiuso-9999/tools/deploy-quote-stati-recesso-chiuso.sh?t=$(date +%s)" | bash
#
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/quote-stati-recesso-chiuso-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP="$(date +%Y%m%d-%H%M%S)"

FILES=(
  "custom/Espo/Custom/Services/ContrattoStatiRules.php"
  "custom/Espo/Custom/Services/ProvvigioneStatusSync.php"
  "custom/Espo/Custom/Hooks/Quote/SyncStatiContrattoFinanziamento.php"
  "custom/Espo/Custom/Hooks/Quote/SetPresentedWhenNumeroContratto.php"
  "custom/Espo/Custom/Hooks/Opportunity/SyncStatiContrattoFinanziamento.php"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Opportunity.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Quote.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Opportunity.json"
  "custom/Espo/Custom/Resources/i18n/en_US/Opportunity.json"
  "custom/Espo/Custom/Tools/CrmKpi/Alerts.php"
  "custom/Espo/Custom/Classes/Select/Quote/PrimaryFilters/ContrattiSospesiFinanziamento.php"
  "custom/Espo/Custom/Classes/Select/Quote/PrimaryFilters/ContrattiBacklog.php"
  "custom/Espo/Custom/Classes/Select/Opportunity/PrimaryFilters/ContrattiBacklog.php"
  "tools/backfill-stati-contratto-finanziamento.php"
)

cd "${CRM_ROOT}"

echo "=== Quote 3 stati (enum + condizioni) — BRANCH=${BRANCH} ==="

echo ""
echo "=== PASSO 0 — Backup ==="
SESSION="backup_dev/_sessions/${STAMP}_quote-stati-tre-campi"
mkdir -p "${SESSION}" tools

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
echo "=== FATTO codice ==="
echo "Prossimo (dry-run, nessuna modifica dati):"
echo "  php tools/backfill-stati-contratto-finanziamento.php --dry-run"
