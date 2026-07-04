#!/usr/bin/env bash
# Hotfix provvigioni: hook Espo 10, layout subpanel, base calcolo, nome.
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

LEGACY_HOOK="custom/Espo/Custom/Hooks/Provvigione/BeforeSaveLegacy.php"
if [[ -f "${LEGACY_HOOK}" ]]; then
  mv "${LEGACY_HOOK}" "${LEGACY_HOOK}.bak.$(date +%s)"
  echo "Spostato ${LEGACY_HOOK} in backup"
fi

fetch() {
  local rel="$1"
  mkdir -p "$(dirname "${rel}")"
  curl -fsSL "${BASE}/${rel}?t=$(date +%s)" -o "${rel}"
  echo "OK ${rel}"
}

fetch "client/custom/src/handlers/quote/ricalcola-provvigioni.js"
fetch "custom/Espo/Custom/Services/ProvvigioneManager.php"
fetch "custom/Espo/Custom/Hooks/Provvigione/AccrualAndAmount.php"
fetch "custom/Espo/Custom/Hooks/Quote/BeforeSave.php"
fetch "custom/Espo/Custom/Hooks/Quote/AfterSaveTotaleProvvigioni.php"
fetch "custom/Espo/Custom/Resources/metadata/entityDefs/Provvigione.json"
fetch "custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json"
fetch "custom/Espo/Custom/Resources/layouts/Quote/relationships/provvigioni.json"
fetch "custom/Espo/Custom/Resources/layouts/Provvigione/listSmall.json"
fetch "custom/Espo/Custom/Resources/layouts/Provvigione/list.json"
fetch "custom/Espo/Custom/Resources/layouts/Provvigione/detail.json"
fetch "custom/Espo/Custom/Resources/i18n/it_IT/Provvigione.json"
fetch "database/2026-07-04-provvigione-base-calcolo.sql"

php clear_cache.php
php rebuild.php

echo "OK — Ctrl+F5, elimina provvigioni vecchie, Ricalcola provvigioni sul contratto"
