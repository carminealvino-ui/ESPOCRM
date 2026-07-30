#!/usr/bin/env bash
# Deploy + guida backfill legacy Opportunity → Quote
#
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/backfill-quote-legacy-da-opportunita-9999/tools/deploy-backfill-quote-legacy-da-opportunita.sh" \
#     -o /tmp/deploy-backfill-legacy.sh && bash /tmp/deploy-backfill-legacy.sh

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/backfill-quote-legacy-da-opportunita-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

echo "=== Deploy backfill legacy Opp→Quote → ${CRM_ROOT} ==="

mkdir -p "${CRM_ROOT}/tools"
curl -fsSL -o "${CRM_ROOT}/tools/backfill-quote-legacy-da-opportunita.php" \
  "${BASE}/tools/backfill-quote-legacy-da-opportunita.php?t=$(date +%s)"

grep -q "Opportunity.installazione" \
  "${CRM_ROOT}/tools/backfill-quote-legacy-da-opportunita.php" || {
  echo "ERRORE: script backfill non aggiornato" >&2
  exit 1
}

echo ""
echo "Script pronto. Esegui IN ORDINE:"
echo ""
echo "1) Anteprima (nessuna scrittura):"
echo "   php ${CRM_ROOT}/tools/backfill-quote-legacy-da-opportunita.php --dry-run"
echo ""
echo "2) Applica solo campi vuoti (consigliato):"
echo "   php ${CRM_ROOT}/tools/backfill-quote-legacy-da-opportunita.php --apply"
echo ""
echo "3) Solo se vuoi SOVRASCRIVERE anche valori già presenti sul Contratto:"
echo "   php ${CRM_ROOT}/tools/backfill-quote-legacy-da-opportunita.php --apply --overwrite"
echo ""
echo "Opzioni utili:"
echo "   --limit=50"
echo "   --quote-id=ID"
echo "   --no-normalize   (non applica ContrattoStatiRules)"
