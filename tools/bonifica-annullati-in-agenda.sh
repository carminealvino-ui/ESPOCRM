#!/usr/bin/env bash
# Aggiorna TUTTI gli appuntamenti annullati (Non Svolto) ancora in agenda:
# 1) riassegna ad admin di sistema
# 2) rimuove eventi da Google Calendar del consulente
#
# Uso (in produzione, dopo il deploy del fix):
#   bash tools/bonifica-annullati-in-agenda.sh
#   bash tools/bonifica-annullati-in-agenda.sh --dry-run

set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
PHP_BIN="${PHP_BIN:-php}"
DRY_RUN=0

for arg in "$@"; do
  if [[ "${arg}" == "--dry-run" ]]; then
    DRY_RUN=1
  fi
done

if [[ ! -d "${CRM_ROOT}" ]]; then
  echo "ERRORE: cartella CRM non trovata: ${CRM_ROOT}" >&2
  exit 1
fi

cd "${CRM_ROOT}"

if [[ ! -f tools/bonifica-appuntamento-not-held-admin.php ]]; then
  echo "ERRORE: manca tools/bonifica-appuntamento-not-held-admin.php — esegui prima il deploy" >&2
  exit 1
fi

if [[ ! -f tools/verify-appuntamento-annullato-admin-fix.php ]]; then
  echo "ERRORE: manca verify — esegui prima il deploy" >&2
  exit 1
fi

echo "=== Verifica fix installato ==="
"${PHP_BIN}" tools/verify-appuntamento-annullato-admin-fix.php

echo ""
echo "=== Fase 1: riassegnazione Non Svolto → admin ==="
if [[ "${DRY_RUN}" -eq 1 ]]; then
  "${PHP_BIN}" tools/bonifica-appuntamento-not-held-admin.php --dry-run --force
else
  "${PHP_BIN}" tools/bonifica-appuntamento-not-held-admin.php --apply --force --quiet
fi

echo ""
echo "=== Fase 2: rimozione da Google Calendar (Not Held) ==="
if [[ ! -f tools/bonifica-appuntamento-google-calendar.php ]]; then
  echo "ATTENZIONE: tools/bonifica-appuntamento-google-calendar.php assente — salto fase Google"
else
  if [[ "${DRY_RUN}" -eq 1 ]]; then
    "${PHP_BIN}" tools/bonifica-appuntamento-google-calendar.php --dry-run --only-not-held
  else
    "${PHP_BIN}" tools/bonifica-appuntamento-google-calendar.php --apply --only-not-held
  fi
fi

echo ""
if [[ "${DRY_RUN}" -eq 1 ]]; then
  echo "Dry-run completato. Per applicare:"
  echo "  bash tools/bonifica-annullati-in-agenda.sh"
else
  echo "Fatto. Ricarica il calendario: gli annullati non devono più restare in agenda del consulente."
fi
