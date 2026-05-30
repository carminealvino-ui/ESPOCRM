#!/usr/bin/env bash
# Backup stato FUNZIONANTE Appuntamento (dopo fix produzione).
# Salva in custom/backup-layouts/ + copie in backup_dev/ (convenzione DATA_FIX_*).
#
#   cd ~/public_html/crm/mec-group
#   bash tools/backup-appuntamento-stabile.sh
#   bash tools/backup-appuntamento-stabile.sh post-test-maggio
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
LABEL="${1:-appuntamento-stabile-funzionante}"
FIX="appuntamento-produzione-ok"
DATA="$(date +%Y%m%d-%H%M%S)"

cd "${CRM_ROOT}" || exit 1

mkdir -p tools backup_dev/Appuntamento/{hooks,layouts,metadata/clientDefs,metadata/entityDefs,metadata/logicDefs,client/detail,client/handlers,client/runtime} \
  backup_dev/client/fields custom/backup-layouts

# Aggiorna lista file in backup-produzione (curl da main se script vecchio)
if ! grep -q 'reminders-disabled' tools/backup-produzione.sh 2>/dev/null; then
  echo "WARN: eseguire deploy o aggiornare tools/backup-produzione.sh da GitHub"
fi

echo "=== 1/2 Backup rollback (custom/backup-layouts/) ==="
STAMP="$(bash tools/backup-produzione.sh "${LABEL}" | tail -1)"
echo "${STAMP}" > custom/backup-layouts/LAST_APPUNTAMENTO_STABLE_BACKUP.txt
echo "${STAMP}" > custom/backup-layouts/LAST_APPUNTAMENTO_BACKUP.txt

echo ""
echo "=== 2/2 Snapshot backup_dev/ (stato stabile) ==="

copy_dev() {
  local aggiornamento="$1"
  local rel_src="$2"
  local obiettivo="$3"
  local src="${CRM_ROOT}/${rel_src}"
  local dest_dir="${CRM_ROOT}/backup_dev/Appuntamento/${aggiornamento}"
  local ext="${obiettivo##*.}"
  local base="${obiettivo%.*}"
  local out

  if [[ ! -f "${src}" ]]; then
    echo "SKIP ${rel_src}"
    return 0
  fi

  mkdir -p "${dest_dir}"
  if [[ "${base}" == "${ext}" ]] || [[ "${obiettivo}" != *.* ]]; then
    out="${dest_dir}/${DATA}_${FIX}_${aggiornamento}_${obiettivo}"
  else
    out="${dest_dir}/${DATA}_${FIX}_${aggiornamento}_${base}.${ext}"
  fi
  cp -a "${src}" "${out}"
  echo "OK ${out#${CRM_ROOT}/}"
}

copy_dev hooks "custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php" "GlobalLogic.php"
copy_dev layouts "custom/Espo/Custom/Resources/layouts/Appuntamento/list.json" "list.json"
copy_dev layouts "custom/Espo/Custom/Resources/layouts/Appuntamento/detail.json" "detail.json"
copy_dev layouts "custom/Espo/Custom/Resources/layouts/Appuntamento/detailSmall.json" "detailSmall.json"
copy_dev layouts "custom/Espo/Custom/Resources/layouts/Appuntamento/massUpdate.json" "massUpdate.json"
copy_dev metadata/clientDefs "custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json" "Appuntamento.json"
copy_dev metadata/entityDefs "custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json" "Appuntamento.json"
copy_dev metadata/logicDefs "custom/Espo/Custom/Resources/metadata/logicDefs/Appuntamento.json" "Appuntamento.json"
copy_dev client/detail "client/custom/src/views/appuntamento/record/detail.js" "detail.js"
copy_dev client/detail "client/custom/src/views/appuntamento/record/edit.js" "edit.js"
copy_dev client/detail "client/custom/src/views/appuntamento/record/edit-small.js" "edit-small.js"

mkdir -p "${CRM_ROOT}/backup_dev/client/fields"
for f in fornitore-partner-cascade product-brand-by-partner product-category-by-brand reminders-disabled; do
  src="${CRM_ROOT}/client/custom/src/views/fields/${f}.js"
  if [[ -f "${src}" ]]; then
    cp -a "${src}" "${CRM_ROOT}/backup_dev/client/fields/${DATA}_${FIX}_fields_${f}.js"
    echo "OK backup_dev/client/fields/${DATA}_${FIX}_fields_${f}.js"
  fi
done

ARCHIVE="${CRM_ROOT}/backup_dev/_archives/backup-appuntamento-stabile-${DATA}.tar.gz"
tar -czf "${ARCHIVE}" -C "${CRM_ROOT}/custom/backup-layouts" "${STAMP}" 2>/dev/null || true
if [[ -f "${ARCHIVE}" ]]; then
  echo "OK archivio: ${ARCHIVE#${CRM_ROOT}/}"
fi

echo ""
echo "=== Completato ==="
echo "Rollback rapido:  bash tools/rollback-produzione.sh ${STAMP}"
echo "Backup layouts:   custom/backup-layouts/${STAMP}/"
echo "backup_dev:       file con prefisso ${DATA}_${FIX}_*"
echo "Ultimo stabile:   custom/backup-layouts/LAST_APPUNTAMENTO_STABLE_BACKUP.txt → ${STAMP}"
