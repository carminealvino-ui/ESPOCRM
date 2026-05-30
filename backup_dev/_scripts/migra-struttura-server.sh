#!/usr/bin/env bash
# Sposta backup dalla root piatta di backup_dev → sottocartelle per entità.
# Sicuro: non cancella, solo mv. Eseguire da CRM_ROOT.
#
#   cd ~/public_html/crm/mec-group
#   bash backup_dev/_scripts/migra-struttura-server.sh
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$(pwd)}"
ROOT="${CRM_ROOT}/backup_dev"

if [[ ! -d "${ROOT}" ]]; then
  echo "ERRORE: ${ROOT} non trovata"
  exit 1
fi

cd "${ROOT}"

ensure_dirs() {
  local entities=(Appuntamento Opportunity Prospect Lead Quote Product FornitorePartner ProductBrand)
  local types=(hooks layouts metadata/entityDefs metadata/logicDefs metadata/clientDefs client client/detail client/handlers client/runtime)
  for e in "${entities[@]}"; do
    for t in "${types[@]}"; do
      mkdir -p "${e}/${t}"
    done
  done
  mkdir -p _scripts _archives
}

move_glob() {
  local pattern="$1"
  local dest_dir="$2"
  shopt -s nullglob
  local files=( ${pattern} )
  shopt -u nullglob
  for f in "${files[@]}"; do
    [[ -f "${f}" ]] || continue
    [[ "${f}" == */* ]] && continue
    mkdir -p "${dest_dir}"
    mv -v "${f}" "${dest_dir}/"
  done
}

ensure_dirs

echo "=== Appuntamento ==="
move_glob 'backup-appuntamento-globallogic-*.php' 'Appuntamento/hooks'
move_glob 'backup-appuntamento-entitydefs-*.json' 'Appuntamento/metadata/entityDefs'
move_glob 'backup-appuntamento-logicdefs-*.json' 'Appuntamento/metadata/logicDefs'
move_glob 'backup-appuntamento-clientdefs-*.json' 'Appuntamento/metadata/clientDefs'

echo "=== Opportunity ==="
move_glob 'backup-opportunity-globallogic-*.php' 'Opportunity/hooks'
move_glob 'backup-opportunity-entitydefs-*.json' 'Opportunity/metadata/entityDefs'
move_glob 'backup-opportunity-logicdefs-*.json' 'Opportunity/metadata/logicDefs'
move_glob 'backup-opportunity-clientdefs-*.json' 'Opportunity/metadata/clientDefs'
move_glob 'backup-opportunity-detail-*.js' 'Opportunity/client/detail'
move_glob 'backup-opportunity-create-contratto-handler-*.js' 'Opportunity/client/handlers'
move_glob 'backup-runtime-opportunity-*.js' 'Opportunity/client/runtime'
move_glob 'backup-create-contratto-*.php' 'Opportunity/hooks'
move_glob 'AutoCreateQuote_*' 'Opportunity/hooks'

echo "=== Prospect / Lead / altri ==="
move_glob 'backup-prospect-*.json' 'Prospect/metadata'
move_glob 'backup-prospect-*.js' 'Prospect/client'
move_glob 'backup-client-product-category-by-brand-*.js' 'Product/client'

echo "=== Structure (metadata / layouts) ==="
shopt -s nullglob
for f in backup-structure-custom-Espo-Custom-Resources-*; do
  [[ -f "${f}" ]] || continue
  if [[ "${f}" == *-layouts-Appuntamento-* ]]; then
    mkdir -p Appuntamento/layouts
    base="${f#*layouts-Appuntamento-}"
    mv -v "${f}" "Appuntamento/layouts/${base}"
  elif [[ "${f}" == *-entityDefs-Appuntamento* ]]; then
    mv -v "${f}" "Appuntamento/metadata/entityDefs/Appuntamento.json"
  elif [[ "${f}" == *-layouts-Opportunity-* ]]; then
    base="${f#*layouts-Opportunity-}"
    mkdir -p Opportunity/layouts
    mv -v "${f}" "Opportunity/layouts/${base}"
  elif [[ "${f}" == *-entityDefs-Opportunity* ]]; then
    mv -v "${f}" "Opportunity/metadata/entityDefs/Opportunity.json"
  elif [[ "${f}" == *-layouts-Lead-* ]]; then
    base="${f#*layouts-Lead-}"
    mkdir -p Lead/layouts
    mv -v "${f}" "Lead/layouts/${base}"
  elif [[ "${f}" == *-entityDefs-Lead* ]]; then
    mv -v "${f}" "Lead/metadata/entityDefs/Lead.json"
  elif [[ "${f}" == *-layouts-Prospect-* ]]; then
    base="${f#*layouts-Prospect-}"
    mkdir -p Prospect/layouts
    mv -v "${f}" "Prospect/layouts/${base}"
  elif [[ "${f}" == *-entityDefs-Prospect* ]]; then
    mv -v "${f}" "Prospect/metadata/entityDefs/Prospect.json"
  elif [[ "${f}" == *-layouts-FornitorePartner-* ]]; then
    base="${f#*layouts-FornitorePartner-}"
    mkdir -p FornitorePartner/layouts
    mv -v "${f}" "FornitorePartner/layouts/${base}"
  elif [[ "${f}" == *-entityDefs-FornitorePartner* ]]; then
    mv -v "${f}" "FornitorePartner/metadata/entityDefs/FornitorePartner.json"
  elif [[ "${f}" == *-layouts-ProductBrand-* ]]; then
    base="${f#*layouts-ProductBrand-}"
    mkdir -p ProductBrand/layouts
    mv -v "${f}" "ProductBrand/layouts/${base}"
  else
    mkdir -p _flat-legacy
    mv -v "${f}" "_flat-legacy/"
  fi
done
shopt -u nullglob

if [[ -f deploy-produzione-sync-branch.sh ]]; then
  mkdir -p _scripts
  mv -v deploy-produzione-sync-branch.sh _scripts/ 2>/dev/null || true
fi

echo ""
echo "Migrazione completata. Elenco:"
find Appuntamento Opportunity Prospect Lead -type f 2>/dev/null | head -40
echo "..."
echo "File rimasti in root:"
find . -maxdepth 1 -type f | head -20
