#!/usr/bin/env bash
# Ripristino Appuntamento da backup_dev + fix Crea/Duplica.
#
#   cd ~/public_html/crm/mec-group
#   bash backup_dev/_scripts/migra-struttura-server.sh
#   curl -fsSL ".../tools/restore-appuntamento-da-backup-hooks-cleanup.sh?t=$(date +%s)" | bash
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
HOOK_ROOT="${CRM_ROOT}/backup_dev"
APP="${HOOK_ROOT}/Appuntamento"
BRANCH="${BRANCH:-main}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}" || exit 1

mkdir -p tools custom/backup-layouts
for tool in backup-produzione.sh rollback-produzione.sh backup-dev-save.sh; do
  curl -fsSL "${BASE}/tools/${tool}?t=$(date +%s)" -o "${CRM_ROOT}/tools/${tool}" 2>/dev/null || true
  chmod +x "${CRM_ROOT}/tools/${tool}" 2>/dev/null || true
done

if [[ -f "${HOOK_ROOT}/_scripts/migra-struttura-server.sh" ]]; then
  bash "${HOOK_ROOT}/_scripts/migra-struttura-server.sh" 2>/dev/null || true
fi

echo "=== 1/4 Backup (custom/backup-layouts/) ==="
STAMP="$(bash "${CRM_ROOT}/tools/backup-produzione.sh" pre-restore-hooks-cleanup 2>/dev/null | tail -1 || date +%Y%m%d-%H%M%S)"
echo "Backup ID: ${STAMP}"

pick_newest() {
  local dir="$1"
  local pattern="$2"
  ls -1 "${dir}"/${pattern} 2>/dev/null | sort -r | head -1
}

restore_if_exists() {
  local src="$1"
  local dest="$2"
  if [[ -f "${src}" ]]; then
    mkdir -p "$(dirname "${dest}")"
    cp -a "${src}" "${dest}"
    echo "OK $(basename "${src}") → ${dest#${CRM_ROOT}/}"
    return 0
  fi
  return 1
}

echo ""
echo "=== 2/4 Ripristino Appuntamento da ${APP}/ ==="

GLOG="$(pick_newest "${APP}/hooks" '*globallogic*2042*')"
[[ -z "${GLOG}" ]] && GLOG="$(pick_newest "${APP}/hooks" '*globallogic*1955*')"
[[ -z "${GLOG}" ]] && GLOG="$(pick_newest "${APP}/hooks" '*globallogic*1.7.0*')"
[[ -z "${GLOG}" ]] && GLOG="$(pick_newest "${HOOK_ROOT}" 'backup-appuntamento-globallogic*2042*')"

if [[ -n "${GLOG}" ]]; then
  restore_if_exists "${GLOG}" "${CRM_ROOT}/custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php"
else
  echo "SKIP GlobalLogic (nessun backup hooks)"
fi

LOGIC="$(pick_newest "${APP}/metadata/logicDefs" '*')"
[[ -z "${LOGIC}" ]] && LOGIC="$(pick_newest "${HOOK_ROOT}" 'backup-appuntamento-logicdefs*')"
restore_if_exists "${LOGIC}" "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/logicDefs/Appuntamento.json" || true

ENTITY="$(pick_newest "${APP}/metadata/entityDefs" '*')"
[[ -z "${ENTITY}" ]] && ENTITY="${APP}/metadata/entityDefs/Appuntamento.json"
[[ ! -f "${ENTITY}" ]] && ENTITY="$(pick_newest "${HOOK_ROOT}" 'backup-appuntamento-entitydefs*')"

if restore_if_exists "${ENTITY}" "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json"; then
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
    echo 'OK: rimosse view crm:meeting su date/reminders' . PHP_EOL;
  "
else
  curl -fsSL "${BASE}/custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json?t=$(date +%s)" \
    -o "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json"
  echo "OK entityDefs da GitHub main"
fi

echo ""
echo "=== 3/4 clientDefs standard (Crea / Duplica) ==="
curl -fsSL "${BASE}/custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json?t=$(date +%s)" \
  -o "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json"

curl -fsSL "${BASE}/client/custom/src/views/appuntamento/record/detail.js?t=$(date +%s)" \
  -o "${CRM_ROOT}/client/custom/src/views/appuntamento/record/detail.js" 2>/dev/null || true

rm -f "${CRM_ROOT}/client/custom/src/views/appuntamento/record/edit.js" \
      "${CRM_ROOT}/client/custom/src/views/appuntamento/record/edit-small.js" 2>/dev/null || true

echo ""
echo "=== 4/4 Rebuild ==="
php command.php rebuild
php command.php clearCache 2>/dev/null || true
rm -rf data/cache/*

echo ""
echo "Fatto. Ctrl+F5."
echo "Rollback: bash tools/rollback-produzione.sh ${STAMP}"
