#!/usr/bin/env bash
# Fix riassegnazione admin su appuntamento annullato (Not Held).
# Verifica hook 1.7.9 dopo installazione.
#
# Uso:
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-appuntamento-annullato-admin-9999/tools/deploy-fix-appuntamento-annullato-admin.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="cursor/fix-appuntamento-annullato-admin-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"
TS="$(date +%s)"
LEGACY_HOOKS="custom/Espo/Custom/Resources/metadata/Appuntamento/hooks.json"

if [[ ! -d "${CRM_ROOT}" ]]; then
  echo "ERRORE: cartella CRM non trovata: ${CRM_ROOT}" >&2
  exit 1
fi

cd "${CRM_ROOT}"

PHP_BIN="${PHP_BIN:-php}"

fetch() {
  local path="$1"
  mkdir -p "$(dirname "${path}")"
  curl -fL --retry 8 --retry-all-errors --retry-delay 2 --retry-max-time 120 \
    "${BASE}/${path}?t=${TS}" -o "${path}"
  echo "OK ${path}"
}

FILES=(
  custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php
  custom/Espo/Custom/Hooks/Appuntamento/NotHeldAdminAssignAfterSave.php
  custom/Espo/Custom/Hooks/Appuntamento/GoogleCalendarSync.php
  custom/Espo/Custom/Hooks/Appuntamento/GoogleCalendarSyncBeforeGlobal.php
  custom/Espo/Custom/Hooks/Appuntamento/GoogleCalendarSyncAfterGlobal.php
  custom/Espo/Custom/Resources/metadata/hooks/Appuntamento.json
  custom/Espo/Custom/Services/AppuntamentoGoogleSync.php
  tools/bonifica-appuntamento-not-held-admin.php
  tools/diagnose-appuntamento-assegnazione.php
  tools/verify-appuntamento-annullato-admin-fix.php
)

for rel in "${FILES[@]}"; do
  fetch "${rel}"
done

if [[ -f "${LEGACY_HOOKS}" ]]; then
  rm -f "${LEGACY_HOOKS}"
  echo "RIMOSSO ${LEGACY_HOOKS} (puntava a Common\\GlobalLogic inesistente)"
fi

"${PHP_BIN}" clear_cache.php
"${PHP_BIN}" rebuild.php
"${PHP_BIN}" clear_cache.php

echo ""
echo "=== Verifica installazione (hook 1.7.10) ==="
"${PHP_BIN}" tools/verify-appuntamento-annullato-admin-fix.php

echo ""
echo "=== Hook registrati ==="
grep -E 'NotHeldAdminAssign|GlobalLogic|GoogleCalendarSyncAfterGlobal' \
  custom/Espo/Custom/Resources/metadata/hooks/Appuntamento.json || true

echo ""
echo "=== Bonifica (dry-run) ==="
"${PHP_BIN}" tools/bonifica-appuntamento-not-held-admin.php --dry-run --force | tail -5

echo ""
echo "Applica su tutti i Non Svolto:"
echo "  php tools/bonifica-appuntamento-not-held-admin.php --apply --force"
echo ""
echo "Dopo il save, HookVersion in UI deve essere 1.7.10 (non 1.7.7)."
