#!/usr/bin/env bash
# =============================================================================
# Backfill prezzoCodice + listPrice su prodotti ARIEL (anche COMBO vs PROMO 1+1).
# Usa sync-listino con NO_CREATE=1 e matching denominazione normalizzata.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL ".../tools/fase-3h-backfill-prezzo-codice-prodotto.sh" -o /tmp/fase-3h.sh
#   DRY_RUN=1 bash /tmp/fase-3h.sh
#   bash /tmp/fase-3h.sh
# =============================================================================
set -euo pipefail

export NO_CREATE=1
export DRY_RUN="${DRY_RUN:-0}"
export FILTER_CATEGORIA="${FILTER_CATEGORIA:-}"

CRM_DIR="${CRM_DIR:-$HOME/public_html/crm/mec-group}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" 2>/dev/null && pwd || true)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." 2>/dev/null && pwd || echo "$CRM_DIR")"

echo "=== Fase 3h: backfill prezzoCodice prodotti (listino 07/05/2026) ==="
echo "CRM_DIR=$CRM_DIR DRY_RUN=$DRY_RUN"

cd "$CRM_DIR"

if [[ -f "${REPO_ROOT}/tools/sync-listino-prodotti.php" ]]; then
  SYNC_PHP="${REPO_ROOT}/tools/sync-listino-prodotti.php"
  CSV="${REPO_ROOT}/database/data/listino-ariel-prodotti-07052026.csv"
else
  BRANCH="${GITHUB_BRANCH:-cursor/provvigioni-manuali-fase-a-9999}"
  RAW="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"
  curl -fsSL "${RAW}/tools/sync-listino-prodotti.php" -o /tmp/sync-listino-prodotti.php
  curl -fsSL "${RAW}/database/data/listino-ariel-prodotti-07052026.csv" -o /tmp/listino-ariel-prodotti-07052026.csv
  SYNC_PHP="/tmp/sync-listino-prodotti.php"
  CSV="/tmp/listino-ariel-prodotti-07052026.csv"
fi

ARGS=(
  --file="$CSV"
  --price-book-id=07ce1b326cd314ca2
  --converti-iva-esclusa
)

if [[ "$DRY_RUN" == "1" ]]; then
  ARGS+=(--dry-run)
fi

php "$SYNC_PHP" "${ARGS[@]}"

echo "Fatto. Verificare prodotto COMBO: prezzoCodice netto atteso ~4000 (da 4400 IVI listino)."
