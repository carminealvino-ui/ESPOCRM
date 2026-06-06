#!/usr/bin/env bash
# Deploy fix Provvigione (Cliente, Contratto, nome) — come sempre: curl da GitHub, niente git sul server.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-provvigione-campi-collegati-9999/tools/deploy-provvigione-campi-collegati.sh?t=$(date +%s)" | bash
#
# NON tocca entityDefs né layout Provvigione (modifiche Entity Manager restano intatte).
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/fix-provvigione-campi-collegati-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}" || exit 1

echo "=== Deploy Provvigione (branch ${BRANCH}) ==="
curl -fsSL "${BASE}/tools/applica-provvigione-campi-collegati.php?t=$(date +%s)" -o /tmp/applica-provvigione-campi-collegati.php
php /tmp/applica-provvigione-campi-collegati.php

echo ""
echo "=== Backfill provvigioni esistenti ==="
php tools/backfill-provvigione-campi-collegati.php --verbose

echo ""
echo "Fatto. Ctrl+F5 nel browser."
