#!/usr/bin/env bash
# Bonifica Google Calendar — sessione guidata (un comando per fase).
#
#   cd ~/public_html/crm/mec-group
#   bash tools/run-google-sync-bonifica-session.sh           # dry-run
#   bash tools/run-google-sync-bonifica-session.sh --apply   # esegue
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
USER_ID="${GOOGLE_CALENDAR_USER_ID:-67c93e694705fde80}"
FROM_DATE="${BONIFICA_FROM_DATE:-2026-06-01}"
TO_DATE="${BONIFICA_TO_DATE:-2026-06-14}"
BONIFICA="${CRM_ROOT}/tools/bonifica-appuntamento-google-calendar.php"

APPLY_FLAG="--dry-run"
if [[ "${1:-}" == "--apply" ]]; then
  APPLY_FLAG="--apply"
fi

if [[ ! -f "${BONIFICA}" ]]; then
  echo "ERRORE: ${BONIFICA} non trovato. Esegui deploy google-sync prima."
  exit 1
fi

run_phase() {
  local title="$1"
  shift
  echo ""
  echo "=== ${title} (${APPLY_FLAG}) ==="
  (cd "${CRM_ROOT}" && php "${BONIFICA}" ${APPLY_FLAG} "$@")
}

echo "Google sync bonifica — consulente ${USER_ID}"
echo "Range duplicati: ${FROM_DATE} → ${TO_DATE}"
if [[ "${APPLY_FLAG}" == "--dry-run" ]]; then
  echo "Modalità: ANTEPRIMA (aggiungi --apply per eseguire)"
else
  echo "Modalità: APPLY — modifiche reali"
fi

run_phase "1/5 Purge ghost Espo" \
  --only-purge-ghosts --user-id="${USER_ID}"

run_phase "2/5 Backfill syncConGoogle" \
  --backfill-sync-flag --user-id="${USER_ID}"

run_phase "3/5 Bonifica completa" \
  --verbose --user-id="${USER_ID}"

run_phase "4/5 Purge duplicati Google" \
  --only-purge-duplicates \
  --from-date="${FROM_DATE}" --to-date="${TO_DATE}" \
  --user-id="${USER_ID}"

run_phase "5/5 Push mancanti" \
  --only-push --verbose --user-id="${USER_ID}"

echo ""
if [[ "${APPLY_FLAG}" == "--dry-run" ]]; then
  echo "Fine anteprima. Se OK:"
  echo "  bash tools/run-google-sync-bonifica-session.sh --apply"
else
  echo "Bonifica completata. Verifica Google Calendar + lista Appuntamenti."
fi
