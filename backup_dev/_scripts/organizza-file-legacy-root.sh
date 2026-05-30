#!/usr/bin/env bash
# Seconda passata: file vecchi ancora in root di backup_dev → cartelle per entità.
# (backup-prod-*, backup-linea-*, GlobalLogic_OLD, .sql, .tar.gz, …)
#
#   cd ~/public_html/crm/mec-group
#   bash backup_dev/_scripts/organizza-file-legacy-root.sh
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$(pwd)}"
ROOT="${CRM_ROOT}/backup_dev"

if [[ ! -d "${ROOT}" ]]; then
  echo "ERRORE: ${ROOT} non trovata"
  exit 1
fi

cd "${ROOT}"

ensure_dirs() {
  local entities=(Appuntamento Opportunity Prospect Lead Quote Product FornitorePartner ProductBrand Provvigione InvitoAFatturare)
  local types=(hooks layouts metadata/entityDefs metadata/logicDefs metadata/clientDefs client client/detail client/handlers client/runtime)
  for e in "${entities[@]}"; do
    for t in "${types[@]}"; do
      mkdir -p "${e}/${t}"
    done
  done
  mkdir -p _archives _archives/sql _flat-legacy _scripts client
}

move_root_glob() {
  local pattern="$1"
  local dest_dir="$2"
  shopt -s nullglob
  for f in ${pattern}; do
    [[ -f "${f}" ]] || continue
    mkdir -p "${dest_dir}"
    mv -v "${f}" "${dest_dir}/"
  done
  shopt -u nullglob
}

move_root_file() {
  local f="$1"
  local dest_dir="$2"
  [[ -f "${f}" ]] || return 0
  mkdir -p "${dest_dir}"
  mv -v "${f}" "${dest_dir}/"
}

ensure_dirs

echo "=== Appuntamento (prod / legacy) ==="
move_root_glob 'backup-prod-appuntamento-*' 'Appuntamento/hooks'
move_root_glob 'backup-appuntamento-*' 'Appuntamento/hooks'
move_root_glob '*appuntamento*globallogic*' 'Appuntamento/hooks'
move_root_glob 'GlobalLogic_OLD*' 'Appuntamento/hooks'
move_root_file 'Appuntamento.json' 'Appuntamento/metadata/entityDefs'

echo "=== Opportunity (prod / legacy) ==="
move_root_glob 'backup-prod-opportunity-detail-*' 'Opportunity/client/detail'
move_root_glob 'backup-prod-opportunity-clientdefs-*' 'Opportunity/metadata/clientDefs'
move_root_glob 'backup-prod-create-contratto-*' 'Opportunity/client/handlers'
move_root_glob 'backup-prod-opportunity-*' 'Opportunity/hooks'
move_root_glob 'backup-opportunity-*' 'Opportunity/hooks'
move_root_glob 'backup-create-contratto-*' 'Opportunity/hooks'
move_root_glob 'backup-runtime-opportunity-*' 'Opportunity/client/runtime'
move_root_glob '*opportunity*globallogic*' 'Opportunity/hooks'
move_root_glob 'AutoCreateQuote_*' 'Opportunity/hooks'

echo "=== Quote / Contratto ==="
move_root_glob 'backup-prod-quote-*' 'Quote/metadata'
move_root_glob 'backup-quote-*' 'Quote/metadata'

echo "=== Provvigione / Invito ==="
move_root_glob 'backup-Provvigione*' 'Provvigione/hooks'
move_root_glob 'backup-provigione-*' 'Provvigione/hooks'
move_root_glob 'backup-InvitoAFatturare*' 'InvitoAFatturare/hooks'
move_root_glob 'backup-invito*' 'InvitoAFatturare/hooks'

echo "=== Product / linea / categoria ==="
move_root_glob 'backup-client-product-*' 'Product/client'
move_root_glob 'backup-product-*' 'Product/client'
move_root_glob 'backup-linea-*' 'Opportunity/metadata'
move_root_glob '*linea-prodotto*' 'Opportunity/metadata'
move_root_glob '*linea-categoria*' 'Opportunity/metadata'

echo "=== Lead / Prospect ==="
move_root_file 'Lead.json' 'Lead/metadata/entityDefs'
move_root_glob 'backup-prospect-*' 'Prospect/metadata'
move_root_glob 'backup-lead-*' 'Lead/metadata'

echo "=== Client globale / archivi ==="
move_root_glob 'backup-prod-app-client-*' 'client'
move_root_glob 'backup-custom-client-*' '_archives'
move_root_glob 'backup-prod-custom-*' '_archives'
move_root_glob '*.tar.gz' '_archives'
move_root_glob '*.sql' '_archives/sql'

echo "=== File non classificati → _flat-legacy ==="
shopt -s nullglob
for f in *; do
  [[ -f "${f}" ]] || continue
  mkdir -p _flat-legacy
  mv -v "${f}" "_flat-legacy/"
done
shopt -u nullglob

echo ""
echo "Fine. File ancora in root:"
find . -maxdepth 1 -type f 2>/dev/null | wc -l
find . -maxdepth 1 -type f 2>/dev/null | head -10
