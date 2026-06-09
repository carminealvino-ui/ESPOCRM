#!/usr/bin/env bash
# LEGGI PRIMA: ../REGOLE-PRODUZIONE/README.md (un passo alla volta, backup, istruzioni complete)
#
# Scarica script tools/ sul server (la cartella tools/ NON è parte del deploy Espo).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/main/tools/bootstrap-server-tools.sh?t=$(date +%s)" | bash
#
# Poi:
#   bash tools/backup-quote-layouts.sh
#   bash tools/backup-account-layouts.sh
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-main}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

if [[ ! -f "${CRM_ROOT}/command.php" ]]; then
  echo "ERRORE: eseguire da root CRM (command.php mancante in ${CRM_ROOT})"
  exit 1
fi

cd "${CRM_ROOT}"
mkdir -p tools tools/layouts-samples/Quote exports/sync

TOOL_FILES=(
  "tools/backup-dev-save.sh"
  "tools/backup-dev-batch.sh"
  "tools/backup-manifests/google-sync.files"
  "tools/backup-manifests/prospect-form-ui.files"
  "tools/install-backup-dev-tools.sh"
  "tools/deploy-prospect-appuntamento-form-ui.sh"
  "tools/backup-quote-layouts.sh"
  "tools/restore-quote-layouts.sh"
  "tools/backup-account-layouts.sh"
  "tools/backup-contratto-prima-deploy.sh"
  "tools/backup-produzione.sh"
  "tools/apply-quote-detail-prezzi-sample.sh"
  "tools/deploy-contratto-prezzi-curl.sh"
  "tools/deploy-emergency-restore-crm-ui.sh"
  "tools/deploy-crea-prodotto-button.sh"
  "tools/applica-account-subpanel-produzione.sh"
  "tools/backfill-appuntamento-account-link.php"
  "tools/sync-custom-prod-repo.php"
  "tools/sync-custom-prod-repo.config.json"
  "tools/sync-apply-delta-cpanel.sh"
  "tools/sync-push-github-cpanel.sh"
  "tools/bootstrap-regole-produzione.sh"
  "tools/deploy-fix-appuntamento-google-sync.sh"
  "tools/deploy-bonifica-appuntamento-google-calendar.sh"
  "tools/bonifica-appuntamento-google-calendar.php"
  "tools/fix-contratto-importo-minusplus-standalone.php"
  "tools/layouts-samples/Quote/detail-prezzi-minusplus.json"
)

# backup-account e subpanel Account: su main dopo merge PR; finché solo su branch feature:
EXTRA_BRANCH="${EXTRA_BRANCH:-cursor/account-subpanel-appuntamenti-contratti-9999}"
EXTRA_BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${EXTRA_BRANCH}"
EXTRA_FILES=(
  "tools/backup-account-layouts.sh"
  "tools/backup-contratto-prima-deploy.sh"
  "tools/applica-account-subpanel-produzione.sh"
  "tools/backfill-appuntamento-account-link.php"
)

fetch_one() {
  local base="$1"
  local rel="$2"
  local dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  if curl -fsSL "${base}/${rel}?t=$(date +%s)" -o "${dest}"; then
    [[ "${rel}" == *.sh ]] && chmod +x "${dest}"
    echo "OK ${rel}"
    return 0
  fi
  return 1
}

echo "=== Bootstrap tools (branch ${BRANCH}) ==="
for rel in "${TOOL_FILES[@]}"; do
  fetch_one "${BASE}" "${rel}" || echo "SKIP ${rel} (non su ${BRANCH})"
done

echo "=== Script su branch ${EXTRA_BRANCH} (se mancanti) ==="
for rel in "${EXTRA_FILES[@]}"; do
  if [[ ! -f "${CRM_ROOT}/${rel}" ]]; then
    fetch_one "${EXTRA_BASE}" "${rel}" || echo "SKIP ${rel}"
  fi
done

echo ""
echo "Script in ${CRM_ROOT}/tools/"
echo ""
echo "=== Passo 0 OBBLIGATORIO: backup in backup_dev/ ==="
echo "  bash tools/backup-dev-batch.sh FIX --manifest tools/backup-manifests/google-sync.files"
echo "  bash tools/backup-dev-save.sh ENTITA FIX TIPO FILE"
echo "  bash tools/backup-quote-layouts.sh"
echo "  bash tools/backup-account-layouts.sh"
echo ""
echo "  php tools/sync-custom-prod-repo.php status --branch=main"
