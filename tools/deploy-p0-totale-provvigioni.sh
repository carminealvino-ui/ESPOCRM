#!/usr/bin/env bash
# P0.1 — Fix Provvigioni Totali (importoConsolidato) con backup obbligatorio.
#
# Solo codice sync/pricing. Niente migrazione bulk qui.
# Dopo verifica: backfill UN contratto (vedi fine output).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-totale-provvigioni-p0-9999/tools/deploy-p0-totale-provvigioni.sh?t=$(date +%s)" | bash
#
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/fix-totale-provvigioni-p0-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP="$(date +%Y%m%d-%H%M%S)"

FILES=(
  "custom/Espo/Custom/Services/QuoteProvvigioniSync.php"
  "custom/Espo/Custom/Services/ProvvigioneManager.php"
  "custom/Espo/Custom/Services/ProvvigioneStatusSync.php"
  "custom/Espo/Custom/Services/QuotePricingCalculator.php"
  "tools/backfill-quote-totale-provvigioni.php"
)

cd "${CRM_ROOT}"

echo "=== P0.1 Totale Provvigioni — BRANCH=${BRANCH} ==="
echo "CRM_ROOT=${CRM_ROOT}"

# --- PASSO 0: BACKUP ---
echo ""
echo "=== PASSO 0 — Backup in backup_dev/ ==="
mkdir -p backup_dev/_sessions tools

SESSION="backup_dev/_sessions/${STAMP}_p0-totale-provvigioni"
mkdir -p "${SESSION}"

backup_one() {
  local rel="$1"
  local src="${CRM_ROOT}/${rel}"
  if [[ ! -f "${src}" ]]; then
    echo "SKIP (assente): ${rel}"
    return 0
  fi
  mkdir -p "${SESSION}/$(dirname "${rel}")"
  cp -a "${src}" "${SESSION}/${rel}"
  echo "BACKUP ${rel}"
}

for rel in "${FILES[@]}"; do
  backup_one "${rel}"
done

{
  echo "stamp=${STAMP}"
  echo "branch=${BRANCH}"
  echo "files:"
  printf '  %s\n' "${FILES[@]}"
} > "${SESSION}/log.txt"
echo "Sessione: ${SESSION}"

echo ""
echo "=== Verifica backup ==="
find "${SESSION}" -type f | sed "s|^${CRM_ROOT}/||" | head -20
ls -la backup_dev/_sessions/ | tail -5

# --- PASSO 1: download ---
echo ""
echo "=== PASSO 1 — Download da ${BRANCH} ==="
for rel in "${FILES[@]}"; do
  dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=${STAMP}" -o "${dest}"
  echo "OK ${rel}"
done

# --- Rebuild ---
echo ""
echo "=== Clear cache + rebuild ==="
php clear_cache.php
php rebuild.php

echo ""
echo "=== FATTO P0.1 codice ==="
echo "NON è stata fatta migrazione bulk."
echo ""
echo "Prossimo comando (UN contratto, dopo verifica UI):"
echo "  php tools/backfill-quote-totale-provvigioni.php --codice=Contratto_00153"
echo ""
echo "Rollback:"
echo "  cp -a ${SESSION}/custom/Espo/Custom/Services/*.php custom/Espo/Custom/Services/"
echo "  cp -a ${SESSION}/tools/backfill-quote-totale-provvigioni.php tools/ 2>/dev/null || true"
echo "  php clear_cache.php && php rebuild.php"
