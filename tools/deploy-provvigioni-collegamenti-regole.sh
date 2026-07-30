#!/usr/bin/env bash
# Deploy fix provvigioni:
# - collega cliente/contratto/opportunità in modo robusto
# - rende regola/tipo/regime selezionabili in edit
# - abilita RegolaProvvigionale come entità gestibile (ACL ruoli)
#
# Uso:
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/provvigioni-collegamenti-regole-9999/tools/deploy-provvigioni-collegamenti-regole.sh" \
#     -o /tmp/deploy-provvigioni.sh && bash /tmp/deploy-provvigioni.sh

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/provvigioni-collegamenti-regole-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

echo "=== Deploy provvigioni collegamenti + regole (${BRANCH}) ==="
cd "${CRM_ROOT}"

FILES=(
  "custom/Espo/Custom/Hooks/Provvigione/AccrualAndAmount.php"
  "custom/Espo/Custom/Hooks/Provvigione/BeforeSave.php"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Provvigione.json"
  "client/custom/src/views/provvigione/record/edit.js"
  "custom/Espo/Custom/Resources/client/custom/src/views/provvigione/record/edit.js"
  "tools/backfill-provvigione-campi-collegati.php"
  "tools/enable-regola-provvigionale-acl.php"
)

for rel in "${FILES[@]}"; do
  dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=$(date +%s)" -o "${dest}"
  echo "OK ${rel}"
done

grep -q "opportunitaId" "${CRM_ROOT}/custom/Espo/Custom/Hooks/Provvigione/AccrualAndAmount.php" || {
  echo "ERRORE: hook AccrualAndAmount non aggiornato" >&2
  exit 1
}

for js in \
  "${CRM_ROOT}/client/custom/src/views/provvigione/record/edit.js" \
  "${CRM_ROOT}/custom/Espo/Custom/Resources/client/custom/src/views/provvigione/record/edit.js"
do
  grep -q "setFieldReadOnly('tassoProvvigioni')" "${js}" || {
    echo "ERRORE: file edit provvigione non aggiornato (${js})" >&2
    exit 1
  }

  if grep -q "setFieldReadOnly('regolaProvvigionale')" "${js}"; then
    echo "ERRORE: ${js} ancora readOnly su regolaProvvigionale" >&2
    exit 1
  fi
done

echo "=== Rebuild + cache ==="
if [[ -f "${CRM_ROOT}/command.php" ]]; then
  (cd "${CRM_ROOT}" && php command.php rebuild && php command.php clearCache)
elif [[ -f "${CRM_ROOT}/rebuild.php" ]]; then
  (cd "${CRM_ROOT}" && php rebuild.php && php clear_cache.php) || true
fi

echo "=== ACL RegolaProvvigionale ==="
php tools/enable-regola-provvigionale-acl.php || true

echo ""
echo "=== Backfill consigliato ==="
echo "Anteprima:"
echo "  php tools/backfill-provvigione-campi-collegati.php --dry-run --all"
echo "Applica:"
echo "  php tools/backfill-provvigione-campi-collegati.php --all"
echo ""
echo "Poi logout/login + hard refresh."
