#!/usr/bin/env bash
# Deploy schema stati semplificato su Contratto (Quote).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/quote-stati-condizionale-9999/tools/deploy-quote-stati-condizionale.sh?t=$(date +%s)" | bash
#
# Variabili opzionali:
#   SKIP_MIGRATION=1   — non esegue migrate-quote-stati-semplificati.php
#   DRY_RUN_MIGRATION=1 — solo anteprima migrazione dati

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/quote-stati-condizionale-9999"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP=$(date +%Y%m%d-%H%M%S)
LOCAL_BACKUP="${CRM_ROOT}/backup/quote-stati-${STAMP}"

FILES=(
  "custom/Espo/Custom/Resources/layouts/Quote/detail.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json"
  "custom/Espo/Custom/Resources/metadata/logicDefs/Quote.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Quote.json"
  "custom/Espo/Custom/Actions/Opportunity/CreateContratto.php"
  "database/2026-06-25-quote-stati-semplificati.sql"
  "tools/migrate-quote-stati-semplificati.php"
  "tools/verify-quote-stati-deploy.php"
)

echo "=== Deploy stati Contratto (${BRANCH}) ==="
echo "Root: ${CRM_ROOT}"
echo ""

if [[ ! -f "${CRM_ROOT}/bootstrap.php" ]]; then
  echo "ERRORE: bootstrap.php non trovato in ${CRM_ROOT}" >&2
  exit 1
fi

mkdir -p "${LOCAL_BACKUP}"

for rel in "${FILES[@]}"; do
  src="${CRM_ROOT}/${rel}"
  if [[ -f "${src}" ]]; then
    mkdir -p "${LOCAL_BACKUP}/$(dirname "${rel}")"
    cp -a "${src}" "${LOCAL_BACKUP}/${rel}"
    echo "BACKUP ${rel}"
  fi
done

echo ""
echo "=== Download da GitHub ==="

for rel in "${FILES[@]}"; do
  dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=${STAMP}" -o "${dest}"
  echo "OK ${rel}"
done

echo ""
echo "=== Verifica file ==="
(cd "${CRM_ROOT}" && php tools/verify-quote-stati-deploy.php)

if [[ "${SKIP_MIGRATION:-0}" != "1" ]]; then
  echo ""
  echo "=== Migrazione dati contratti esistenti ==="
  MIGRATE_ARGS=()
  if [[ "${DRY_RUN_MIGRATION:-0}" == "1" ]]; then
    MIGRATE_ARGS+=(--dry-run)
  fi
  (cd "${CRM_ROOT}" && php tools/migrate-quote-stati-semplificati.php "${MIGRATE_ARGS[@]}")
else
  echo ""
  echo "SKIP_MIGRATION=1 — migrazione dati saltata"
fi

echo ""
echo "=== Cache e rebuild ==="
(cd "${CRM_ROOT}" && php clear_cache.php && php rebuild.php)

echo ""
echo "=== Deploy completato ==="
echo "Backup locale: ${LOCAL_BACKUP}"
echo "Browser: Ctrl+Shift+R su un Contratto"
echo ""
echo "Controllo manuale:"
echo "  - Stato: Bozza | In lavorazione | Appuntamento fissato | Installato | Invalido"
echo "  - Stato Contratto: Inserito | In lavorazione | Approvato | Sospeso | Recesso | Annullato"
echo "  - Stato Finanziamento (se finanziamento): OTP | valutazione | documentazione | Approvato | Respinto"
