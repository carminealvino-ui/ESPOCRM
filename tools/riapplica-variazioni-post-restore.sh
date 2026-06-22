#!/usr/bin/env bash
# Riapplica in produzione le variazioni post-restore Softaculous (2026-06-19).
# UN PASSO ALLA VOLTA — confermare ogni fase prima di --step=N+1
#
#   cd ~/public_html/crm/mec-group
#   bash tools/riapplica-variazioni-post-restore.sh              # mostra piano
#   bash tools/riapplica-variazioni-post-restore.sh --step=0     # backup Softaculous reminder
#   bash tools/riapplica-variazioni-post-restore.sh --step=1     # Call esito
#   bash tools/riapplica-variazioni-post-restore.sh --step=2     # rebuild + fix assegnazione
#   bash tools/riapplica-variazioni-post-restore.sh --step=3     # KPI codice (no tab)
#   bash tools/riapplica-variazioni-post-restore.sh --step=4     # KPI dashlet merge (opzionale)
#   bash tools/riapplica-variazioni-post-restore.sh --step=5     # Report Vendite (solo elenco)
#
# Branch: cursor/crm-kpi-dashlet-9999 (include Call esito + KPI)
# User:  carmine_alvino (default)

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
=== Piano riapplicazione variazioni (post-restore Softaculous) ===

Base restore: backup Softaculous ~2026-06-19 (tab dashboard intatti).

| Step | Cosa | Tocca tab? | Tocca DB dati? |
|------|------|------------|----------------|
| 0 | Backup dashboard + Softaculous manuale | no | no |
| 1 | Call esito / popup / dashlet Calls | no* | no |
| 2 | clear_cache + rebuild + fix assegnazione Call | no | sì (Call esistenti) |
| 3 | KPI codice (API + filtri, NO dashboard) | no | no |
| 4 | KPI dashlet nel tab CRM (merge) | sì | no |
| 5 | Report Vendite Mese (--reports-only) | no | sì (tabella report) |

* Il dashlet Calls sul tab esistente si aggiorna; i tab non vengono sostituiti.

Opzionale (branch separato):
  deploy-appuntamento-calendario-esito.sh → click calendario → pannello esito

Comandi:
  bash tools/riapplica-variazioni-post-restore.sh --step=0
  bash tools/riapplica-variazioni-post-restore.sh --step=1
  ...

Documentazione: REGOLE-PRODUZIONE/10-RIAPPLICA-VARIAZIONI-DOPO-RESTORE.md
EOF
}

step_0() {
  echo "=== STEP 0 — Backup obbligatorio ==="
  echo ""
  echo "1. Softaculous → Backup manuale EspoCRM (annotare nome file .tar.gz)"
  echo "2. Backup dashboard utente:"
  fetch_tool "tools/backup-dashboard-utente.sh"
  fetch_tool "tools/rollback-dashboard-pre-kpi.php"
  fetch_tool "tools/lib/dashboard-report-helpers.php"
  chmod +x tools/backup-dashboard-utente.sh tools/rollback-dashboard-pre-kpi.php
  bash tools/backup-dashboard-utente.sh "${USER_NAME}"
  echo ""
  echo "STOP: inviare screenshot backup OK prima dello step 1."
}

step_1() {
  echo "=== STEP 1 — Deploy Call esito / popup / dashlet Calls ==="
  fetch_tool "tools/deploy-call-esito-canale-contatto.sh"
  chmod +x tools/deploy-call-esito-canale-contatto.sh
  bash tools/deploy-call-esito-canale-contatto.sh "${CRM_ROOT}" "${BRANCH}"
  echo ""
  echo "STOP: php clear_cache.php && php rebuild.php — poi verificare popup Call e dashlet."
}

step_2() {
  echo "=== STEP 2 — Rebuild + fix assegnazione Call ==="
  php clear_cache.php && php rebuild.php
  fetch_tool "tools/fix-call-assignment-from-appuntamento.php"
  php tools/fix-call-assignment-from-appuntamento.php
  echo ""
  echo "STOP: verificare Call auto-create assegnate a utente appuntamento, non Admin."
}

step_3() {
  echo "=== STEP 3 — KPI codice (senza tab dashboard) ==="
  fetch_tool "tools/deploy-crm-kpi-dashlet.sh"
  chmod +x tools/deploy-crm-kpi-dashlet.sh
  bash tools/deploy-crm-kpi-dashlet.sh "${CRM_ROOT}" "${BRANCH}"
  php clear_cache.php && php rebuild.php
  echo ""
  echo "STOP: NON eseguire applica-dashboard ancora. Verificare che i tab siano invariati."
}

step_4() {
  echo "=== STEP 4 — Dashlet KPI nel tab CRM (merge, opzionale) ==="
  bash tools/backup-dashboard-utente.sh "${USER_NAME}"
  fetch_tool "tools/applica-dashboard-crm-kpi.php"
  chmod +x tools/applica-dashboard-crm-kpi.php
  php tools/applica-dashboard-crm-kpi.php --user="${USER_NAME}"
  php clear_cache.php
  echo ""
  echo "STOP: Ctrl+F5 tab CRM — deve esserci KPI + tutti i dashlet precedenti."
  echo "Rollback: php tools/rollback-dashboard-pre-kpi.php --user=${USER_NAME} --restore-latest"
}

step_5() {
  echo "=== STEP 5 — Report Vendite Mese (solo elenco CRM) ==="
  fetch_tool "tools/crea-report-vendite-mese.php"
  fetch_tool "tools/report-templates/vendite-mese.json"
  chmod +x tools/crea-report-vendite-mese.php
  php tools/crea-report-vendite-mese.php --reports-only --force --user="${USER_NAME}"
  php clear_cache.php
  echo ""
  echo "STOP: CRM → Report → verificare 4 report Vendite Mese. Tab dashboard NON modificati."
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
  5) step_5 ;;
  *) echo "Step sconosciuto: ${STEP} (0-5)" >&2; exit 1 ;;
esac
