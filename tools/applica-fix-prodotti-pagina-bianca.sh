#!/usr/bin/env bash
# Fix pagina Prodotti bianca: clientDefs Product non deve sostituire bottomPanels Sales.
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/productprice-dual-iva-listino-codice-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}" || exit 1

echo "=== Backup clientDefs Product ==="
TS="$(date +%Y%m%d-%H%M%S)"
BK="custom/backup-layouts/product-clientdefs-${TS}"
mkdir -p "${BK}"
cp -a custom/Espo/Custom/Resources/metadata/clientDefs/Product.json "${BK}/" 2>/dev/null || true
echo "Backup: ${BK}/Product.json"

echo "=== Deploy fix clientDefs Product (solo relationshipPanels.contratti) ==="
curl -fsSL "${BASE}/custom/Espo/Custom/Resources/metadata/clientDefs/Product.json?t=$(date +%s)" \
  -o custom/Espo/Custom/Resources/metadata/clientDefs/Product.json
echo "OK clientDefs/Product.json"

php command.php rebuild
php command.php clear-cache 2>/dev/null || true
rm -rf data/cache/*
echo ""
echo "Fix applicato. Apri Prodotti e premi Ctrl+F5."
echo "Rollback: cp ${BK}/Product.json custom/Espo/Custom/Resources/metadata/clientDefs/Product.json && php command.php rebuild && rm -rf data/cache/*"
