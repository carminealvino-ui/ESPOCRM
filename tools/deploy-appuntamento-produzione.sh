#!/usr/bin/env bash
# PRODUZIONE — ripristina gestione Appuntamenti (Crea, Modifica, Duplica, scheda).
# Non usa crm:views/meeting/* (modulo client CRM assente su molti server MEC).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/main/tools/deploy-appuntamento-produzione.sh?t=$(date +%s)" | bash
#
# Rollback:
#   bash tools/rollback-produzione.sh
#   bash tools/rollback-produzione.sh "$(cat custom/backup-layouts/LAST_APPUNTAMENTO_BACKUP.txt)"
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

echo "=== 1/4 Backup (custom/backup-layouts/) ==="
STAMP="$(bash "${CRM_ROOT}/tools/backup-produzione.sh" Appuntamento-produzione | tail -1)"
echo "Backup ID: ${STAMP}"
echo "${STAMP}" > "${CRM_ROOT}/custom/backup-layouts/LAST_APPUNTAMENTO_BACKUP.txt"

echo ""
echo "=== 2/4 Deploy metadata + client Appuntamento ==="

FILES=(
  "custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/metadata/logicDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/metadata/hooks/Appuntamento.json"
  "custom/Espo/Custom/Resources/metadata/selectDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/metadata/scopes/Appuntamento.json"
  "custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php"
  "client/custom/src/views/appuntamento/record/detail.js"
  "client/custom/src/views/appuntamento/record/edit.js"
  "client/custom/src/views/appuntamento/record/edit-small.js"
  "client/custom/src/views/fields/fornitore-partner-cascade.js"
  "client/custom/src/views/fields/product-brand-by-partner.js"
  "client/custom/src/views/fields/product-category-by-brand.js"
  "client/custom/src/views/fields/reminders-disabled.js"
  "custom/Espo/Custom/Resources/layouts/Appuntamento/detail.json"
  "custom/Espo/Custom/Resources/layouts/Appuntamento/detailSmall.json"
  "custom/Espo/Custom/Resources/layouts/Appuntamento/massUpdate.json"
)

for rel in "${FILES[@]}"; do
  dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=$(date +%s)" -o "${dest}"
  echo "OK ${rel}"
done

# View legacy che richiedono modulo CRM meeting (404 in produzione)
rm -f "${CRM_ROOT}/client/custom/src/views/appuntamento/modals/detail.js" 2>/dev/null || true

# Rimuove eventuali view crm:meeting su entityDefs (backup vecchi)
php -r "
\$f = '${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json';
\$j = json_decode(file_get_contents(\$f), true);
if (!is_array(\$j)) { exit(0); }
foreach (['dateStart', 'dateEnd'] as \$field) {
  if (isset(\$j['fields'][\$field]['view'])) {
    unset(\$j['fields'][\$field]['view']);
  }
}
if (isset(\$j['fields']['reminders'])) {
  \$rv = \$j['fields']['reminders']['view'] ?? '';
  if (\$rv === '' || str_contains(\$rv, 'crm:')) {
    \$j['fields']['reminders']['view'] = 'custom:views/fields/reminders-disabled';
  }
  \$j['fields']['reminders']['layoutDetailDisabled'] = true;
  \$j['fields']['reminders']['layoutDetailSmallDisabled'] = true;
  \$j['fields']['reminders']['layoutMassUpdateDisabled'] = true;
}
file_put_contents(\$f, json_encode(\$j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
echo 'OK entityDefs: date senza crm:meeting; reminders con vista sicura' . PHP_EOL;
"

echo ""
echo "=== 3/4 Rebuild + cache ==="
php command.php rebuild
php command.php clearCache 2>/dev/null || true
rm -rf data/cache/*

echo ""
echo "=== 4/4 Verifica ==="
CLIENT_DEFS="${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json"
if grep -qE '"edit":\s*"(views/record/edit|custom:views/appuntamento/record/edit)"' "${CLIENT_DEFS}"; then
  echo "OK clientDefs edit → viste standard/custom (no crm:meeting)"
else
  echo "ATTENZIONE: controllare clientDefs edit in ${CLIENT_DEFS}"
fi
if grep -q 'crm:views/meeting' "${CLIENT_DEFS}" 2>/dev/null; then
  echo "ERRORE: clientDefs contiene ancora crm:views/meeting"
  exit 1
fi

echo ""
echo "Fatto. Nel browser: Ctrl+F5 (o svuota cache)."
echo "Prova: elenco Appuntamenti → Crea, apri record → Duplica, Modifica."
echo ""
echo "Calendario: se resta bianco, il server potrebbe non avere client CRM compilato;"
echo "  verifica: ls client/lib/transpiled/modules/crm 2>/dev/null || echo '(assente)'"
echo ""
echo "ROLLBACK:"
echo "  bash tools/rollback-produzione.sh ${STAMP}"
