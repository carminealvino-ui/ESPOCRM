#!/usr/bin/env bash
# Deploy product-category-by-brand.js su tutti i path client usati in produzione.
# Uso (dalla root CRM, es. ~/public_html/crm/mec-group):
#   bash tools/deploy-product-category-by-brand.sh

set -euo pipefail

CRM_ROOT="${1:-$(pwd)}"
BRANCH="${2:-cursor/opportunity-globallogic-9999}"
REPO="carminealvino-ui/ESPOCRM"
FILE="client/custom/src/views/fields/product-category-by-brand.js"
URL="https://raw.githubusercontent.com/${REPO}/${BRANCH}/${FILE}"

TARGETS=(
  "${CRM_ROOT}/client/custom/src/views/fields/product-category-by-brand.js"
  "${CRM_ROOT}/custom/Espo/Custom/Resources/client/custom/src/views/fields/product-category-by-brand.js"
  "${CRM_ROOT}/custom/Espo/Custom/client/custom/src/views/fields/product-category-by-brand.js"
)

echo "==> Download ${URL}"
TMP="$(mktemp)"
curl -fsSL -o "${TMP}" "${URL}"

if ! grep -q 'VERSIONE: 1.6.2' "${TMP}"; then
  echo "ERRORE: file remoto non contiene VERSIONE: 1.6.2" >&2
  exit 1
fi

for target in "${TARGETS[@]}"; do
  mkdir -p "$(dirname "${target}")"
  cp "${TMP}" "${target}"
  echo "OK ${target}"
done

rm -f "${TMP}"

echo ""
echo "==> Verifica versione locale:"
grep 'VERSIONE:' "${TARGETS[0]}" || true

if [[ -f "${CRM_ROOT}/clear_cache.php" ]]; then
  echo ""
  echo "==> clear_cache + rebuild"
  (cd "${CRM_ROOT}" && php clear_cache.php && php rebuild.php)
fi

echo ""
echo "Fatto. Hard refresh browser (Ctrl+F5)."
echo "Nel modal categoria NON deve comparire 'byGruppo' (filtro provvigionale rimosso)."
