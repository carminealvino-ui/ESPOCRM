#!/usr/bin/env bash
# Ripristina il tasto Nascondi sui popup esito (Appuntamento/Call/Meeting/Task)
# senza forzare il salvataggio. Aggiorna SOLO 3 file client — nessun metadata Quote/Task.
#
# PASSO 0 — backup obbligatorio:
#   cd ~/public_html/crm/mec-group
#   bash tools/backup-dev-batch.sh nascondi-popup-esito \
#     --manifest tools/backup-manifests/nascondi-popup-esito.files
#
# PASSO 1 — deploy:
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-nascondi-popup-esito-9999/tools/deploy-fix-nascondi-popup-esito.sh" \
#     -o /tmp/deploy-nascondi.sh && bash /tmp/deploy-nascondi.sh

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/fix-nascondi-popup-esito-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
FIX_TAG="nascondi-popup-esito"

echo "=== Fix Nascondi popup esito → ${CRM_ROOT} (branch ${BRANCH}) ==="

FILES=(
  "client/custom/src/views/appuntamento/popup-notification.js"
  "client/custom/res/templates/appuntamento/popup-notification.tpl"
  "client/custom/src/views/notification/badge.js"
)

has_backup() {
  local sessions="${CRM_ROOT}/backup_dev/_sessions"
  [[ -d "${sessions}" ]] || return 1
  find "${sessions}" -maxdepth 1 -type d -name "*_${FIX_TAG}" 2>/dev/null | grep -q .
}

if [[ "${SKIP_BACKUP_CHECK:-}" != "1" ]] && ! has_backup; then
  echo "ERRORE: manca backup sessione *_${FIX_TAG}." >&2
  echo "Esegui prima:" >&2
  echo "  bash tools/backup-dev-batch.sh ${FIX_TAG} \\" >&2
  echo "    --manifest tools/backup-manifests/nascondi-popup-esito.files" >&2
  echo "Oppure SKIP_BACKUP_CHECK=1 se sai cosa fai." >&2
  exit 1
fi

if has_backup; then
  latest="$(find "${CRM_ROOT}/backup_dev/_sessions" -maxdepth 1 -type d -name "*_${FIX_TAG}" 2>/dev/null | sort -r | head -1)"
  echo "Backup rilevato: ${latest#${CRM_ROOT}/}"
fi
echo ""

for rel in "${FILES[@]}"; do
  target="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${target}")"
  curl -fsSL -o "${target}" "${BASE}/${rel}?t=$(date +%s)"
  echo "OK ${rel}"
done

grep -q "hideEsitoPopup" \
  "${CRM_ROOT}/client/custom/src/views/appuntamento/popup-notification.js" || {
  echo "ERRORE: popup-notification.js senza handler hideEsitoPopup" >&2
  exit 1
}

grep -q 'data-action="hideEsitoPopup"' \
  "${CRM_ROOT}/client/custom/res/templates/appuntamento/popup-notification.tpl" || {
  echo "ERRORE: template senza bottone Nascondi (hideEsitoPopup)" >&2
  exit 1
}

grep -q "parkAllPopupNotificationsTemporarily" \
  "${CRM_ROOT}/client/custom/src/views/notification/badge.js" || {
  echo "ERRORE: badge.js senza parkAllPopupNotificationsTemporarily" >&2
  exit 1
}

grep -q "espoPopupCollapsedWipedSessionV4" \
  "${CRM_ROOT}/client/custom/src/views/notification/badge.js" || {
  echo "ERRORE: badge.js senza wipe sessione V4" >&2
  exit 1
}

grep -q "NON chiamare super.setup" \
  "${CRM_ROOT}/client/custom/src/views/appuntamento/popup-notification.js" || {
  echo "ERRORE: popup-notification.js non deve chiamare super.setup sugli esito" >&2
  exit 1
}

grep -q "onPopupDisplayFinished();" \
  "${CRM_ROOT}/client/custom/src/views/notification/badge.js" || {
  echo "ERRORE: badge.js non sblocca la coda dopo render" >&2
  exit 1
}

grep -q "sendAllEsitoPopupsToBackground" \
  "${CRM_ROOT}/client/custom/src/views/appuntamento/popup-notification.js" || {
  echo "ERRORE: popup-notification.js senza sendAllEsitoPopupsToBackground" >&2
  exit 1
}

grep -q "restoreEsitoPopupsFromBackground" \
  "${CRM_ROOT}/client/custom/src/views/appuntamento/popup-notification.js" || {
  echo "ERRORE: popup-notification.js senza restoreEsitoPopupsFromBackground" >&2
  exit 1
}

# Anti-regressione: afterRender non deve nascondere collapse
if grep -q "find('[data-action=\"collapse\"]').addClass('hidden')" \
  "${CRM_ROOT}/client/custom/src/views/appuntamento/popup-notification.js" \
  || grep -q 'find('\''\[data-action="collapse"\]'\'').addClass('\''hidden'\'')' \
  "${CRM_ROOT}/client/custom/src/views/appuntamento/popup-notification.js"; then
  echo "ERRORE: afterRender nasconde ancora collapse — Nascondi tornerebbe morto" >&2
  exit 1
fi

if [[ -f "${CRM_ROOT}/command.php" ]]; then
  (cd "${CRM_ROOT}" && php command.php rebuild && php command.php clearCache)
elif [[ -f "${CRM_ROOT}/rebuild.php" ]]; then
  (cd "${CRM_ROOT}" && php rebuild.php && php clear_cache.php) || true
fi

echo ""
echo "Deploy OK. Hard refresh browser (Ctrl+Shift+R) o nuova scheda anonima."
echo "Se i popup non tornano, in console browser esegui:"
echo "  sessionStorage.clear(); Object.keys(localStorage).filter(k=>/popup|Collapse|ClosePopup/i.test(k)).forEach(k=>localStorage.removeItem(k)); location.reload();"
echo ""
echo "Nascondi = collapse singolo. Crea Opportunità = nasconde solo il container (temporaneo)."
