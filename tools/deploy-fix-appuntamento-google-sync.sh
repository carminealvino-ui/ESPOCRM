#!/usr/bin/env bash
# Google Calendar: rimuove su Not Held/delete, mantiene Ingestibile, cambio consulente.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/main/tools/deploy-fix-appuntamento-google-sync.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-main}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

FILES=(
  "custom/Espo/Custom/Services/AppuntamentoGoogleSync.php"
  "custom/Espo/Custom/Hooks/Appuntamento/GoogleCalendarSync.php"
  "custom/Espo/Custom/Hooks/Appuntamento/GoogleCalendarSyncBeforeGlobal.php"
  "custom/Espo/Custom/Hooks/Appuntamento/GoogleCalendarSyncAfterGlobal.php"
  "custom/Espo/Custom/Hooks/Appuntamento/PreventDuplicate.php"
  "custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php"
  "custom/Espo/Modules/Google/Hooks/Common/GoogleCalendar.php"
  "custom/Espo/Custom/Resources/metadata/hooks/Appuntamento.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json"
  "tools/bonifica-appuntamento-google-calendar.php"
)

echo "=== Fix Appuntamento Google Calendar sync → ${CRM_ROOT} ==="

has_google_sync_backup() {
  local sessions="${CRM_ROOT}/backup_dev/_sessions"
  [[ -d "${sessions}" ]] || return 1
  local latest
  latest="$(find "${sessions}" -maxdepth 1 -type d -name '*_google-sync' 2>/dev/null | sort -r | head -1)"
  [[ -n "${latest}" && -f "${latest}/manifest.txt" && -f "${latest}/files.list" ]]
}

if [[ "${SKIP_BACKUP_CHECK:-}" == "1" ]]; then
  echo "ATTENZIONE: SKIP_BACKUP_CHECK=1 — deploy senza verifica backup"
elif has_google_sync_backup; then
  latest_session="$(find "${CRM_ROOT}/backup_dev/_sessions" -maxdepth 1 -type d -name '*_google-sync' 2>/dev/null | sort -r | head -1)"
  echo "Backup rilevato: ${latest_session#${CRM_ROOT}/}"
else
  echo ""
  echo "PASSO 0 OBBLIGATORIO — backup in backup_dev/ prima del deploy:"
  echo "  cd ${CRM_ROOT}"
  echo "  bash tools/backup-dev-batch.sh google-sync --manifest tools/backup-manifests/google-sync.files"
  echo ""
  echo "Poi esegui il deploy salvando lo script su disco (NON usare pipe):"
  echo "  curl -fsSL \".../deploy-fix-appuntamento-google-sync.sh\" -o tools/deploy-fix-appuntamento-google-sync.sh"
  echo "  bash tools/deploy-fix-appuntamento-google-sync.sh"
  exit 1
fi
echo ""

for rel in "${FILES[@]}"; do
  target="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${target}")"
  curl -fsSL -o "${target}" "${BASE}/${rel}?t=$(date +%s)"
  echo "OK ${rel}"
done

chmod +x "${CRM_ROOT}/tools/bonifica-appuntamento-google-calendar.php" 2>/dev/null || true

if [[ -f "${CRM_ROOT}/clear_cache.php" ]]; then
  (cd "${CRM_ROOT}" && php clear_cache.php)
  (cd "${CRM_ROOT}" && php rebuild.php)
elif [[ -f "${CRM_ROOT}/command.php" ]]; then
  (cd "${CRM_ROOT}" && php command.php clearCache)
  (cd "${CRM_ROOT}" && php command.php rebuild)
fi

echo ""
echo "Fatto. Ctrl+F5."
echo "Sync Google attivo di default; disattiva syncConGoogle per escludere un appuntamento."
echo "Not Held/annullato o eliminato → rimosso da Google; Ingestibile resta; cambio consulente → spostato."
echo ""
echo "Bonifica eventi orfani su Google:"
echo "  php tools/bonifica-appuntamento-google-calendar.php --dry-run"
echo "  php tools/bonifica-appuntamento-google-calendar.php --apply"
echo "Allinea agenda (rimuove annullati + push venerdì/sabato):"
echo "  php tools/bonifica-appuntamento-google-calendar.php --apply --user-id=67c93e694705fde80"
echo "Solo push mancanti:"
echo "  php tools/bonifica-appuntamento-google-calendar.php --apply --only-push --user-id=67c93e694705fde80"
echo "Dopo deploy, UN COMANDO PER RIGA:"
echo "  php tools/bonifica-appuntamento-google-calendar.php --apply --only-purge-ghosts --user-id=67c93e694705fde80"
echo "  php tools/bonifica-appuntamento-google-calendar.php --apply --backfill-sync-flag --user-id=67c93e694705fde80"
echo "  php tools/bonifica-appuntamento-google-calendar.php --apply --verbose --user-id=67c93e694705fde80"
echo "Duplicati Google (stesso codice + slot):"
echo "  php tools/bonifica-appuntamento-google-calendar.php --dry-run --only-purge-duplicates --from-date=2026-04-20 --to-date=2026-04-27 --user-id=67c93e694705fde80"
echo "  php tools/bonifica-appuntamento-google-calendar.php --apply --only-purge-duplicates --from-date=2026-04-20 --to-date=2026-04-27 --user-id=67c93e694705fde80"
echo "Titoli ghost su Google (APPUNTAMENTO SENZA PROSPECT):"
echo "  php tools/bonifica-appuntamento-google-calendar.php --dry-run --only-purge-google-ghost-titles --from-date=2026-06-01 --to-date=2026-06-14 --user-id=67c93e694705fde80"
echo "  php tools/bonifica-appuntamento-google-calendar.php --apply --only-purge-google-ghost-titles --from-date=2026-06-01 --to-date=2026-06-14 --user-id=67c93e694705fde80"
echo ""
echo "NON interrompere rebuild.php (rischio slim-routes.php corrotto)."
