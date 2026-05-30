#!/usr/bin/env bash
# Ripristino Appuntamento da backup/hooks_cleanup sul server + fix Crea/Duplica (viste standard).
#
# Cartella server: ~/public_html/crm/mec-group/backup/hooks_cleanup/
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/main/tools/restore-appuntamento-da-backup-hooks-cleanup.sh?t=$(date +%s)" | bash
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
HOOK_DIR="${CRM_ROOT}/backup/hooks_cleanup"
BRANCH="${BRANCH:-main}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}" || exit 1

mkdir -p tools custom/backup-layouts
for tool in backup-produzione.sh rollback-produzione.sh; do
  curl -fsSL "${BASE}/tools/${tool}?t=$(date +%s)" -o "${CRM_ROOT}/tools/${tool}" 2>/dev/null || true
  chmod +x "${CRM_ROOT}/tools/${tool}" 2>/dev/null || true
done

echo "=== 1/4 Backup stato attuale (custom/backup-layouts/) ==="
STAMP="$(bash "${CRM_ROOT}/tools/backup-produzione.sh" pre-restore-hooks-cleanup 2>/dev/null | tail -1 || date +%Y%m%d-%H%M%S)"
echo "Backup ID: ${STAMP}"

echo ""
echo "=== 2/4 Ripristino da backup/hooks_cleanup (solo Appuntamento) ==="

restore_file() {
  local src_name="$1"
  local dest_rel="$2"
  local src="${HOOK_DIR}/${src_name}"
  local dest="${CRM_ROOT}/${dest_rel}"

  if [[ ! -f "${src}" ]]; then
    echo "SKIP (manca): ${src_name}"
    return 0
  fi

  mkdir -p "$(dirname "${dest}")"
  cp -a "${src}" "${dest}"
  echo "OK ${src_name} → ${dest_rel}"
}

# GlobalLogic: preferisci il file più recente del 26/05 (20:42 > 19:55)
GLOG=""
if [[ -f "${HOOK_DIR}/backup-appuntamento-globallogic-2026-05-26-2042.php" ]]; then
  GLOG="backup-appuntamento-globallogic-2026-05-26-2042.php"
elif [[ -f "${HOOK_DIR}/backup-appuntamento-globallogic-2026-05-26-1955-pre-leadprospectsync.php" ]]; then
  GLOG="backup-appuntamento-globallogic-2026-05-26-1955-pre-leadprospectsync.php"
elif [[ -f "${HOOK_DIR}/backup-appuntamento-globallogic-1.7.0-category-cascade-stabile.php" ]]; then
  GLOG="backup-appuntamento-globallogic-1.7.0-category-cascade-stabile.php"
fi

if [[ -n "${GLOG}" ]]; then
  restore_file "${GLOG}" "custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php"
else
  echo "ATTENZIONE: nessun backup GlobalLogic trovato in ${HOOK_DIR}"
fi

restore_file "backup-appuntamento-logicdefs-2026-05-26-0629-pre-v1.3.0.json" \
  "custom/Espo/Custom/Resources/metadata/logicDefs/Appuntamento.json"

# entityDefs: ripristina solo se presente; poi togli view CRM meeting (altrimenti form bianco)
if [[ -f "${HOOK_DIR}/backup-appuntamento-entitydefs-2026-05-26-2014.json" ]]; then
  restore_file "backup-appuntamento-entitydefs-2026-05-26-2014.json" \
    "custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json"
  php -r "
    \$f = '${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json';
    \$j = json_decode(file_get_contents(\$f), true);
    if (!is_array(\$j)) { exit(0); }
    foreach (['dateStart', 'dateEnd', 'reminders'] as \$field) {
      if (isset(\$j['fields'][\$field]['view'])) {
        unset(\$j['fields'][\$field]['view']);
      }
    }
    file_put_contents(\$f, json_encode(\$j, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
    echo 'OK: rimosse view crm:meeting dai campi data (entityDefs)' . PHP_EOL;
  "
else
  echo "SKIP entitydefs (usa versione da GitHub senza view meeting)"
  curl -fsSL "${BASE}/custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json?t=$(date +%s)" \
    -o "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json"
  echo "OK entityDefs da GitHub main"
fi

echo ""
echo "=== 3/4 clientDefs: viste STANDARD (Crea + Duplica) — NON da hooks_cleanup ==="
curl -fsSL "${BASE}/custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json?t=$(date +%s)" \
  -o "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json"
echo "OK clientDefs → views/record/edit (da main)"

curl -fsSL "${BASE}/client/custom/src/views/appuntamento/record/detail.js?t=$(date +%s)" \
  -o "${CRM_ROOT}/client/custom/src/views/appuntamento/record/detail.js" 2>/dev/null && echo "OK detail.js custom" || true

rm -f "${CRM_ROOT}/client/custom/src/views/appuntamento/record/edit.js" \
      "${CRM_ROOT}/client/custom/src/views/appuntamento/record/edit-small.js" 2>/dev/null || true

echo ""
echo "=== 4/4 Rebuild ==="
php command.php rebuild
php command.php clearCache 2>/dev/null || true
rm -rf data/cache/*

echo ""
echo "Fatto. Ctrl+F5 → Crea Appuntamento / Duplica."
echo ""
echo "NON ripristinati (non servono ad Appuntamento):"
echo "  - backup-create-contratto-*.php, AutoCreateQuote_*.php → Opportunità"
echo "  - backup-client-product-category-by-brand-*.js → Prodotti"
echo ""
echo "Rollback: bash tools/rollback-produzione.sh ${STAMP}"
