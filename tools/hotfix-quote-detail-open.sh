#!/usr/bin/env bash
# Hotfix: Contratto non apre (detail.js addMenuItem) — ripristina vista Sales default.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/provvigione-regole-contratto-9999/tools/hotfix-quote-detail-open.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/provvigione-regole-contratto-9999"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

cd "${CRM_ROOT}"

LEGACY="client/custom/src/views/quote/record/detail.js"
if [[ -f "${LEGACY}" ]]; then
  mv "${LEGACY}" "${LEGACY}.bak.$(date +%s)"
  echo "Spostato ${LEGACY} in backup"
fi

mkdir -p client/custom/src/handlers/quote
curl -fsSL "${BASE}/client/custom/src/handlers/quote/ricalcola-provvigioni.js?t=$(date +%s)" \
  -o client/custom/src/handlers/quote/ricalcola-provvigioni.js

mkdir -p custom/Espo/Custom/Resources/metadata/clientDefs
curl -fsSL "${BASE}/custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json?t=$(date +%s)" \
  -o custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json

php clear_cache.php
php rebuild.php

echo "OK — riapri Contratto con Ctrl+F5"
