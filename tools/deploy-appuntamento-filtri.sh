#!/usr/bin/env bash
# Deploy filtri Appuntamenti (Svolto / Non Svolto) + etichette.
# Uso: bash tools/deploy-appuntamento-filtri.sh ~/public_html/crm/mec-group

set -euo pipefail

CRM_ROOT="${1:-$(pwd)}"
BRANCH="${2:-cursor/opportunity-globallogic-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

FILES=(
  "custom/Espo/Custom/Classes/Select/Appuntamento/PrimaryFilters/Svolto.php"
  "custom/Espo/Custom/Classes/Select/Appuntamento/PrimaryFilters/NonSvolto.php"
  "custom/Espo/Custom/Resources/metadata/selectDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json"
  "custom/Espo/Custom/Resources/i18n/it_IT/Appuntamento.json"
)

for rel in "${FILES[@]}"; do
  target="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${target}")"
  curl -fsSL -o "${target}" "${BASE}/${rel}"
  echo "OK ${target}"
done

echo ""
echo "Verifica label in clientDefs:"
grep -E '"label"|"name"' "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json" | head -10

if [[ -f "${CRM_ROOT}/clear_cache.php" ]]; then
  (cd "${CRM_ROOT}" && php clear_cache.php && php rebuild.php)
fi

echo ""
echo "Fatto. Ctrl+F5 nel browser."
echo "Menu atteso: Tutti | Svolto | Non Svolto | Condiviso"
