#!/usr/bin/env bash
# Sincronizza statoProvvigione in base allo stato contratto (Quote.status).
#
# Uso:
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/sync-stato-provvigione-contratto-9999/tools/deploy-sync-stato-provvigione-contratto.sh?t=$(date +%s)" | bash
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="cursor/sync-stato-provvigione-contratto-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"
TS="$(date +%s)"

cd "${CRM_ROOT}" || exit 1

echo "=== Sync stato provvigione da stato contratto ==="

fetch() {
  local path="$1"
  mkdir -p "$(dirname "$path")"
  curl -fL --retry 8 --retry-all-errors --retry-delay 2 --retry-max-time 120 \
    "${BASE}/${path}?t=${TS}" -o "${path}"
  echo "OK ${path}"
}

FILES=(
  custom/Espo/Custom/Services/ProvvigioneContractStatusSync.php
  custom/Espo/Custom/Services/ProvvigioneManager.php
  custom/Espo/Custom/Hooks/Quote/SyncProvvigioniStatoFromContratto.php
  custom/Espo/Custom/Hooks/Quote/ProvvigioneConsolidata.php
  tools/backfill-provvigioni-stato-da-contratto.php
)

for rel in "${FILES[@]}"; do
  fetch "${rel}"
done

php clear_cache.php
php rebuild.php
php clear_cache.php

echo ""
echo "=== Backfill stati provvigioni ==="
php tools/backfill-provvigioni-stato-da-contratto.php 2>&1 | tail -20

echo ""
echo "=== Mappatura ==="
echo "  Presentato / In lavorazione → Prevista (forecast)"
echo "  Approvato → Consolidata"
echo "  Installato → In invito a fatturare"
echo "  Recesso / Finanziamento rifiutato / Annullato → provvigioni rimosse"
echo "=== Fine ==="
