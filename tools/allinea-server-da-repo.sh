#!/usr/bin/env bash
# Allinea il server produzione al repository GitHub (repo → server).
# Deploy MIRATO: non sovrascrive tutto custom/, solo i file elencati negli script deploy.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/crm-kpi-dashlet-9999/tools/allinea-server-da-repo.sh?t=$(date +%s)" | bash
#   bash tools/allinea-server-da-repo.sh --step=0
#   bash tools/allinea-server-da-repo.sh --step=1
#   ...
#
# Branch canonico KPI + Call esito + avvisi: cursor/crm-kpi-dashlet-9999
# Documentazione: REGOLE-PRODUZIONE/12-ALLINEA-SERVER-DA-REPO.md

set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/crm-kpi-dashlet-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
USER_NAME="${ESPO_USER:-carmine_alvino}"
STEP=""

for arg in "$@"; do
  case "$arg" in
    --step=*) STEP="${arg#*=}" ;;
    --user=*) USER_NAME="${arg#*=}" ;;
    --branch=*) BRANCH="${arg#*=}" ;;
    --all) STEP="all" ;;
  esac
done

cd "${CRM_ROOT}"

fetch_tool() {
  local rel="$1"
  mkdir -p "$(dirname "${rel}")"
  curl -fsSL "${BASE}/${rel}?t=$(date +%s)" -o "${rel}"
  echo "OK scaricato ${rel}"
}

show_plan() {
  cat <<EOF
=== Allineamento server ← repository GitHub ===

Branch: ${BRANCH}
Root:   ${CRM_ROOT}
User:   ${USER_NAME}

| Step | Azione | Verifica attesa |
|------|--------|-----------------|
| 0 | Backup dashboard + promemoria Softaculous | path backup in backup_dev/ |
| 1 | Deploy Call esito + popup | popup Call all'apertura CRM |
| 2 | Deploy KPI completo (API, dashlet, avvisi, filtri) | file OK in backup/crm-kpi-dashlet/ |
| 3 | clear_cache + rebuild + diagnostica | verify + diagnose OK |
| 4 | Dashlet KPI nel tab CRM (merge, opzionale) | tab CRM = vecchi dashlet + KPI |

Dopo ogni step: screenshot → conferma → step successivo.

Comandi:
  bash tools/allinea-server-da-repo.sh --step=0
  bash tools/allinea-server-da-repo.sh --step=1
  ...

Oppure scarica questo script e gli altri tools dal branch:
  curl -fsSL "${BASE}/tools/bootstrap-server-tools.sh?t=\$(date +%s)" | bash

Rollback dashboard KPI: php tools/rollback-dashboard-pre-kpi.php --user=${USER_NAME} --restore-latest
EOF
}

step_0() {
  echo "=== STEP 0 — Backup obbligatorio ==="
  echo ""
  echo "1. Softaculous → Backup manuale EspoCRM (annotare nome .tar.gz)"
  echo "2. Backup dashboard utente:"
  fetch_tool "tools/backup-dashboard-utente.sh"
  fetch_tool "tools/rollback-dashboard-pre-kpi.php"
  fetch_tool "tools/lib/dashboard-report-helpers.php"
  fetch_tool "tools/allinea-server-da-repo.sh"
  chmod +x tools/backup-dashboard-utente.sh tools/allinea-server-da-repo.sh
  bash tools/backup-dashboard-utente.sh "${USER_NAME}"
  echo ""
  echo "STOP: inviare screenshot backup OK prima dello step 1."
}

step_1() {
  echo "=== STEP 1 — Deploy Call esito / popup ==="
  fetch_tool "tools/deploy-call-esito-canale-contatto.sh"
  chmod +x tools/deploy-call-esito-canale-contatto.sh
  bash tools/deploy-call-esito-canale-contatto.sh "${CRM_ROOT}" "${BRANCH}"
  echo ""
  echo "STOP: php clear_cache.php && php rebuild.php — poi verificare popup Call."
}

step_2() {
  echo "=== STEP 2 — Deploy KPI completo (dashlet + avvisi + filtri) ==="
  fetch_tool "tools/deploy-crm-kpi-dashlet.sh"
  chmod +x tools/deploy-crm-kpi-dashlet.sh
  bash tools/deploy-crm-kpi-dashlet.sh "${CRM_ROOT}" "${BRANCH}"
  echo ""
  echo "STOP: eseguire step 3 (rebuild + verifica)."
}

step_3() {
  echo "=== STEP 3 — Rebuild + verifica ==="
  php clear_cache.php && php rebuild.php
  fetch_tool "tools/verify-crm-kpi-deploy.php"
  fetch_tool "tools/diagnose-crm-kpi-api.php"
  php tools/verify-crm-kpi-deploy.php
  php tools/diagnose-crm-kpi-api.php --user="${USER_NAME}"
  echo ""
  echo "STOP: Ctrl+Shift+R sulla dashboard KPI — controllare tile, funnel e avvisi."
}

step_4() {
  echo "=== STEP 4 — Dashlet KPI nel tab CRM (merge, opzionale) ==="
  bash tools/backup-dashboard-utente.sh "${USER_NAME}"
  fetch_tool "tools/applica-dashboard-crm-kpi.php"
  chmod +x tools/applica-dashboard-crm-kpi.php
  php tools/applica-dashboard-crm-kpi.php --user="${USER_NAME}"
  php clear_cache.php
  echo ""
  echo "STOP: Ctrl+F5 tab CRM — KPI + dashlet precedenti."
  echo "Rollback: php tools/rollback-dashboard-pre-kpi.php --user=${USER_NAME} --restore-latest"
}

run_all() {
  step_0
  echo ""
  echo "ATTENZIONE: step 0 completato. Confermare con screenshot prima di --step=1"
}

if [[ -z "${STEP}" ]]; then
  show_plan
  exit 0
fi

case "${STEP}" in
  0) step_0 ;;
  1) step_1 ;;
  2) step_2 ;;
  3) step_3 ;;
  4) step_4 ;;
  all) run_all ;;
  *) echo "Step sconosciuto: ${STEP} (0-4, all)" >&2; exit 1 ;;
esac
