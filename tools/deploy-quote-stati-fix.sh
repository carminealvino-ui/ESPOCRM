#!/usr/bin/env bash
# Deploy fix stati Contratto (schema semplificato) + migrazione dati.
#
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-quote-stati-regressione-9999/tools/deploy-quote-stati-fix.sh" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/fix-quote-stati-regressione-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

echo "=== Deploy fix stati Contratto ==="
echo "Root: ${CRM_ROOT}"

cd "${CRM_ROOT}"

fetch() {
  curl -fsSL "${BASE}/$1" -o "$1"
  echo "OK $1"
}

fetch custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json
fetch custom/Espo/Custom/Resources/metadata/logicDefs/Quote.json
fetch custom/Espo/Custom/Resources/layouts/Quote/detail.json
fetch custom/Espo/Custom/Resources/i18n/it_IT/Quote.json
fetch custom/Espo/Custom/Hooks/Quote/SetPresentedWhenNumeroContratto.php
fetch custom/Espo/Custom/Hooks/Quote/AssignNumberACodiceContratto.php
fetch custom/Espo/Custom/Hooks/Quote/SyncFinanziamentoFromOpportunity.php
fetch custom/Espo/Custom/Actions/Opportunity/CreateContratto.php
fetch database/2026-06-25-quote-stati-semplificati.sql
fetch tools/migrate-quote-stati-semplificati.php

php tools/migrate-quote-stati-semplificati.php
php clear_cache.php && php rebuild.php

echo "=== Completato ==="
echo "Stato: Bozza | In lavorazione | Appuntamento fissato | Installato | Invalido"
echo "Stato Contratto: Inserito | Chiuso | Sospeso | Recesso | Annullato"
