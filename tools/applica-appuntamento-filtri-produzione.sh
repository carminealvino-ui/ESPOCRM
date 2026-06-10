#!/usr/bin/env bash
# Deploy filtri primari Appuntamento: Pianificato, Svolto, Non Svolto, Ingestibile.
# Uso: cd ~/public_html/crm/mec-group && bash tools/applica-appuntamento-filtri-produzione.sh

set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$(pwd)}"
BRANCH="${BRANCH:-main}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
TS="$(date +%Y%m%d-%H%M%S)"
BACKUP="backup_dev/Appuntamento/filtri-primari-${TS}"

FILES=(
  "custom/Espo/Custom/Classes/Select/Appuntamento/PrimaryFilters/Pianificato.php"
  "custom/Espo/Custom/Classes/Select/Appuntamento/PrimaryFilters/Ingestibile.php"
  "custom/Espo/Custom/Classes/Select/Appuntamento/PrimaryFilters/Svolto.php"
  "custom/Espo/Custom/Classes/Select/Appuntamento/PrimaryFilters/NonSvolto.php"
  "custom/Espo/Custom/Resources/metadata/selectDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Appuntamento.json"
)

echo "=== Backup in ${BACKUP} ==="
mkdir -p "${CRM_ROOT}/${BACKUP}"

for rel in "${FILES[@]}"; do
  src="${CRM_ROOT}/${rel}"
  if [[ -f "${src}" ]]; then
    mkdir -p "${CRM_ROOT}/${BACKUP}/$(dirname "${rel}")"
    cp -a "${src}" "${CRM_ROOT}/${BACKUP}/${rel}"
  fi
done

echo "=== Download da GitHub (${BRANCH}) ==="
for rel in "${FILES[@]}"; do
  target="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${target}")"
  curl -fsSL -o "${target}" "${BASE}/${rel}"
  echo "OK ${rel}"
done

if [[ -f "${CRM_ROOT}/clear_cache.php" ]]; then
  echo "=== Rebuild ==="
  (cd "${CRM_ROOT}" && php clear_cache.php && php rebuild.php)
fi

echo ""
echo "Fatto. Ctrl+F5 nel browser."
echo "Menu atteso: Tutti | Pianificato | Svolto | Non Svolto | Ingestibile | Condiviso"
