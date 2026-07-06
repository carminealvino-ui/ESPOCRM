#!/usr/bin/env bash
# Deploy fix contratto (Quote): ordinamento, finanziamento sotto articoli, provvigioni corrette.
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/fix-contratto-quote-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}" || {
  echo "ERRORE: directory CRM non trovata: ${CRM_ROOT}"
  exit 1
}

echo "=== CRM root: ${CRM_ROOT} ==="
echo "=== Branch: ${BRANCH} ==="

echo ""
echo "=== Backup ==="
TS="$(date +%Y%m%d-%H%M%S)"
BK="custom/backup-layouts/contratto-quote-fix-${TS}"
mkdir -p "${BK}"

FILES=(
  custom/Espo/Custom/Hooks/Quote/BeforeSave.php
  custom/Espo/Custom/Hooks/Quote/AfterSaveTotaleProvvigioni.php
  custom/Espo/Custom/Hooks/Quote/ProvvigioneConsolidata.php
  custom/Espo/Custom/Hooks/Quote/SetPresentedWhenNumeroContratto.php
  custom/Espo/Custom/Hooks/Quote/NormalizeDefaults.php
  custom/Espo/Custom/Hooks/Quote/ComputeImportoSaldo.php
  custom/Espo/Custom/Hooks/Quote/AssignNumberACodiceContratto.php
  custom/Espo/Custom/Hooks/Provvigione/AfterSaveContratto.php
  custom/Espo/Custom/Hooks/Provvigione/AccrualAndAmount.php
  custom/Espo/Custom/Services/ProvvigioneManager.php
  custom/Espo/Custom/Services/RegolaProvvigionaleCalculator.php
  custom/Espo/Custom/Actions/Quote/RicalcolaProvvigioni.php
  custom/Espo/Custom/Controllers/Quote.php
  custom/Espo/Custom/Resources/layouts/Quote/detail.json
  custom/Espo/Custom/Resources/layouts/Quote/bottomPanelsDetail.json
  custom/Espo/Custom/Resources/layouts/Quote/list.json
  custom/Espo/Custom/Resources/layouts/Quote/relationships/provvigioni.json
  custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json
  custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json
  custom/Espo/Custom/Resources/metadata/logicDefs/Quote.json
  custom/Espo/Custom/Resources/metadata/formula/Quote.json
  custom/Espo/Custom/Resources/metadata/app/actions.json
  custom/Espo/Custom/Resources/i18n/it_IT/Quote.json
  client/custom/src/handlers/quote/ricalcola-provvigioni.js
)

for f in "${FILES[@]}"; do
  cp -a "${f}" "${BK}/" 2>/dev/null || true
done
echo "Backup: ${BK}/"

for legacy in \
  custom/Espo/Custom/Hooks/Quote/SyncTotaleProvvigioni.php \
  custom/Espo/Custom/Hooks/Provvigione/UpdateQuoteTotaleProvvigioni.php \
  custom/Espo/Custom/Services/QuoteTotaleProvvigioniService.php \
  custom/Espo/Custom/Resources/layouts/Quote/detailBottom.json
do
  if [[ -f "${legacy}" ]]; then
    cp -a "${legacy}" "${BK}/"
    rm -f "${legacy}"
    echo "RIMOSSO ${legacy}"
  fi
done

fetch() {
  local rel="$1"
  local dir
  dir="$(dirname "${rel}")"
  mkdir -p "${dir}"
  curl -fsSL "${BASE}/${rel}?t=$(date +%s)" -o "${rel}"
  echo "OK ${rel}"
}

echo ""
echo "=== Download da ${BRANCH} ==="
for rel in "${FILES[@]}"; do
  fetch "${rel}"
done

echo ""
echo "=== Verifica file scaricati ==="
curl -fsSL "${BASE}/tools/verify-contratto-quote-deploy.php?t=$(date +%s)" -o tools/verify-contratto-quote-deploy.php
php tools/verify-contratto-quote-deploy.php || {
  echo "ERRORE: verifica deploy fallita — controllare i file sopra"
  exit 1
}

echo ""
echo "=== Backfill totaleProvvigioni (importoConsolidato) ==="
curl -fsSL "${BASE}/tools/backfill-quote-totale-provvigioni.php?t=$(date +%s)" -o tools/backfill-quote-totale-provvigioni.php
php tools/backfill-quote-totale-provvigioni.php

echo ""
echo "=== Rebuild + cache ==="
php clear_cache.php
php rebuild.php
rm -rf data/cache/* 2>/dev/null || true
chmod -R u+rwX data/cache 2>/dev/null || true

echo ""
echo "=== Verifica finale ==="
php tools/verify-contratto-quote-deploy.php

echo ""
echo "Fatto. Ctrl+Shift+R nel browser."
echo "Atteso: Articoli → Finanziamento → Provvigioni; totale = somma importo consolidato."
