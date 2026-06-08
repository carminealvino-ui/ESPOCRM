#!/usr/bin/env bash
# PRODUZIONE: backup automatico + Appuntamento con viste Espo standard (Crea / Duplica).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/main/tools/deploy-appuntamento-emergenza-produzione.sh?t=$(date +%s)" | bash
#
# Rollback:
#   bash tools/rollback-produzione.sh
#   bash tools/rollback-produzione.sh 20260529-143000
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-main}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}" || exit 1

mkdir -p tools custom/backup-layouts
for tool in backup-produzione.sh rollback-produzione.sh; do
  curl -fsSL "${BASE}/tools/${tool}?t=$(date +%s)" -o "${CRM_ROOT}/tools/${tool}"
  chmod +x "${CRM_ROOT}/tools/${tool}"
done

echo "=== 1/3 Backup (custom/backup-layouts/) ==="
STAMP="$(bash "${CRM_ROOT}/tools/backup-produzione.sh" Appuntamento-emergenza | tail -1)"
echo "Backup ID: ${STAMP}"
echo "${STAMP}" > "${CRM_ROOT}/custom/backup-layouts/LAST_APPUNTAMENTO_BACKUP.txt"

echo ""
echo "=== 2/3 Deploy viste standard Appuntamento ==="

FILES=(
  "custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json"
  "client/custom/src/views/appuntamento/record/detail.js"
)

for rel in "${FILES[@]}"; do
  dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=$(date +%s)" -o "${dest}"
  echo "OK ${rel}"
done

# Rimuovi override custom edit se puntano a meeting (evita 404)
rm -f "${CRM_ROOT}/client/custom/src/views/appuntamento/record/edit.js" \
      "${CRM_ROOT}/client/custom/src/views/appuntamento/record/edit-small.js" \
      "${CRM_ROOT}/client/custom/src/views/appuntamento/modals/detail.js" 2>/dev/null || true

echo ""
echo "=== 3/3 Rebuild ==="
php command.php rebuild
php command.php clearCache 2>/dev/null || true
rm -rf data/cache/*

echo ""
echo "=== Verifica ==="
if grep -q '"edit": "views/record/edit"' "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json"; then
  echo "OK clientDefs → views/record/edit"
else
  echo "ATTENZIONE: clientDefs non aggiornato"
fi

echo ""
echo "Fatto. Ctrl+F5 → prova Crea Appuntamento e Duplica."
echo ""
echo "ROLLBACK se qualcosa non va:"
echo "  bash tools/rollback-produzione.sh ${STAMP}"
