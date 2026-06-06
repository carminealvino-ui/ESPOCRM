#!/usr/bin/env bash
# Tutto in un colpo: fix prezzi dual IVA listino (un solo PHP autonomo).
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/productprice-dual-iva-listino-codice-9999}"
URL="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}/tools/fix-prezzi-listino-completo.php"

cd "${CRM_ROOT}" || exit 1
mkdir -p tools

echo "Download fix-prezzi-listino-completo.php ..."
curl -fsSL "${URL}?t=$(date +%s)" -o tools/fix-prezzi-listino-completo.php

echo "Esecuzione..."
php tools/fix-prezzi-listino-completo.php "$@"

echo ""
echo "Rebuild layout/i18n..."
php command.php rebuild
php command.php clear-cache 2>/dev/null || true
rm -rf data/cache/*

echo "Fatto."
