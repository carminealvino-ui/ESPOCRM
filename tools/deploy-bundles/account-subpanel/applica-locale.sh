#!/usr/bin/env bash
# Applica subpanel Account SENZA curl GitHub (repo privato → raw 404).
#
# 1) Carica la cartella tools/deploy-bundles/account-subpanel/ sul server (SFTP/rsync)
#    es. in ~/public_html/crm/mec-group/tools/deploy-bundles/account-subpanel/
# 2) bash tools/deploy-bundles/account-subpanel/applica-locale.sh
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BUNDLE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

cd "${CRM_ROOT}" || exit 1

if [[ ! -f "${BUNDLE}/custom/Espo/Custom/Resources/layouts/Account/bottomPanelsDetail.json" ]]; then
  echo "ERRORE: bundle incompleto in ${BUNDLE}"
  exit 1
fi

mkdir -p tools
cp -f "${BUNDLE}/tools/backup-account-layouts.sh" tools/
cp -f "${BUNDLE}/tools/backfill-appuntamento-account-link.php" tools/
chmod +x tools/backup-account-layouts.sh 2>/dev/null || true

bash tools/backup-account-layouts.sh

copy() {
  local rel="$1"
  local src="${BUNDLE}/${rel}"
  local dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  cp -f "${src}" "${dest}"
  echo "OK ${rel}"
}

echo "=== Account subpanel (deploy locale) ==="
copy "custom/Espo/Custom/Resources/layouts/Account/bottomPanelsDetail.json"
copy "custom/Espo/Custom/Resources/metadata/entityDefs/Account.json"
copy "custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json"
copy "custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php"

php command.php rebuild
rm -rf data/cache/*

echo ""
echo "Fatto. Opzionale backfill appuntamenti:"
echo "  php tools/backfill-appuntamento-account-link.php --account-id=ID_CLIENTE"
echo "Ctrl+Shift+R sulla scheda Cliente."
