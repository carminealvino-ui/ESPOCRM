#!/usr/bin/env bash
# Wrapper: scarica ed esegue il deploy PHP autocontenuto (niente curl nested).
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/productprice-dual-iva-listino-codice-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"
TMP="${TMPDIR:-/tmp}/applica-quote-item-catalog-prices.php"

cd "${CRM_ROOT}" || {
  echo "ERRORE: cartella CRM non trovata: ${CRM_ROOT}" >&2
  exit 1
}

echo "=== Download deploy PHP ==="
curl -fsSL "${BASE}/tools/applica-quote-item-catalog-prices.php?t=$(date +%s)" -o "${TMP}"
echo "OK ${TMP}"

echo ""
echo "=== Esecuzione deploy ==="
CRM_ROOT="${CRM_ROOT}" php "${TMP}"
