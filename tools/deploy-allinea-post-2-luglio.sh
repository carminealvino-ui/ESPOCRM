#!/usr/bin/env bash
# Allineamento produzione post-restore 2 luglio 2026.
#
# A) KPI v2 — tile Lordo/Totale/%, Avvisi, Criticità (etichette)
# B) Appuntamento — sottostato filtrato per stato
# C) Appuntamento — fornitore/brand da Prospect in creazione
# D) Contratto — regole provvigioni (subpanel), rimuove pannello legacy, importi corretti
#
# Uso (tutto):
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/allinea-post-2-luglio-9999/tools/deploy-allinea-post-2-luglio.sh?t=$(date +%s)" | bash
#
# Solo una fase:
#   curl -fsSL ".../deploy-allinea-post-2-luglio.sh?t=$(date +%s)" | bash -s B
#
# Fasi: A | B | C | D | ALL (default)

set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
REPO="carminealvino-ui/ESPOCRM"
STAMP=$(date +%Y%m%d-%H%M%S)
PHASE="${1:-ALL}"

cd "${CRM_ROOT}"

download() {
  local branch="$1"
  local rel="$2"
  local dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "https://raw.githubusercontent.com/${REPO}/${branch}/${rel}?t=${STAMP}" -o "${dest}"
  echo "  OK ${rel}"
}

backup_files() {
  local backup_dir="$1"
  shift
  mkdir -p "${backup_dir}"
  for rel in "$@"; do
    local src="${CRM_ROOT}/${rel}"
    if [[ -f "${src}" ]]; then
      mkdir -p "${backup_dir}/$(dirname "${rel}")"
      cp -a "${src}" "${backup_dir}/${rel}"
      echo "  BACKUP ${rel}"
    fi
  done
}

phase_a_kpi() {
  echo ""
  echo "========== A) KPI — Avvisi, Criticità, etichette Lordo/Totale =========="
  local backup="${CRM_ROOT}/backup_dev/KPI/allinea-${STAMP}"
  local branch_v2="cursor/crm-kpi-dashlet-v2-9999"
  local branch_crit="cursor/crm-kpi-criticita-zero-9999"

  local files_v2=(
    "client/custom/css/crm-kpi-dashlet.css"
    "client/custom/res/templates/dashlets/crm-kpi.tpl"
    "client/custom/src/views/dashlets/crm-kpi.js"
    "client/custom/src/views/dashlets/options/crm-kpi.js"
    "custom/Espo/Custom/Controllers/Appuntamento.php"
    "custom/Espo/Custom/Controllers/CrmKpi.php"
    "custom/Espo/Custom/Services/CrmKpi/CrmKpiService.php"
    "custom/Espo/Custom/Tools/CrmKpi/Alerts.php"
    "custom/Espo/Custom/Tools/CrmKpi/FunnelBuilder.php"
    "custom/Espo/Custom/Tools/CrmKpi/KpiContext.php"
    "custom/Espo/Custom/Tools/CrmKpi/DateRange.php"
    "custom/Espo/Custom/Resources/metadata/dashlets/CrmKpi.json"
    "custom/Espo/Custom/Resources/i18n/it_IT/CrmKpi.json"
    "custom/Espo/Custom/Resources/i18n/it_IT/DashletOptions.json"
    "tools/verify-crm-kpi-deploy.php"
  )

  echo "=== Backup ${backup} ==="
  backup_files "${backup}" "${files_v2[@]}"

  echo "=== Download KPI v2 ==="
  for rel in "${files_v2[@]}"; do
    download "${branch_v2}" "${rel}"
  done

  echo "=== Patch Criticità (conteggio 0) ==="
  for rel in \
    "client/custom/src/views/dashlets/crm-kpi.js" \
    "client/custom/res/templates/dashlets/crm-kpi.tpl" \
    "custom/Espo/Custom/Tools/CrmKpi/Alerts.php"
  do
    download "${branch_crit}" "${rel}"
  done

  echo "Verifica: php tools/verify-crm-kpi-deploy.php"
  echo "Browser: tab KPI → Avvisi + Criticità, etichette Lordo/Totale"
}

phase_b_sottostato() {
  echo ""
  echo "========== B) Appuntamento — sottostato per stato =========="
  local backup="${CRM_ROOT}/backup_dev/Appuntamento/sottostato-allinea-${STAMP}"
  local branch="cursor/invito-a-fatturare-selezione-9999"

  local files=(
    "client/custom/src/helpers/appuntamento-sottostato-map.js"
    "client/custom/src/views/fields/appuntamento-sottostato.js"
    "client/custom/src/views/fields/appuntamento-sottostato-popup.js"
    "client/custom/src/views/appuntamento/popup-notification.js"
    "custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json"
    "custom/Espo/Custom/Resources/metadata/logicDefs/Appuntamento.json"
    "custom/Espo/Custom/Resources/layouts/Appuntamento/detailEsitoPopup.json"
    "custom/Espo/Custom/Resources/metadata/app/popupNotifications.json"
  )

  echo "=== Backup ${backup} ==="
  backup_files "${backup}" "${files[@]}"

  echo "=== Download ==="
  for rel in "${files[@]}"; do
    download "${branch}" "${rel}"
  done

  grep -q "getAllowedForStatus" "${CRM_ROOT}/client/custom/src/views/fields/appuntamento-sottostato.js"
  echo "Verifica: Stato=Non Svolto → sottostato solo valori coerenti"
}

phase_c_prospect_brand() {
  echo ""
  echo "========== C) Appuntamento — fornitore/brand da Prospect =========="
  local backup="${CRM_ROOT}/backup_dev/Appuntamento/prospect-brand-${STAMP}"
  local branch_invito="cursor/invito-a-fatturare-selezione-9999"
  local branch_cal="cursor/fix-appuntamento-calendario-500-9999"

  local files=(
    "custom/Espo/Custom/Services/Prospect.php"
    "custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php"
    "custom/Espo/Custom/Services/LeadProspectSync.php"
    "custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json"
    "client/custom/src/helpers/appuntamento-prospect-sync.js"
    "client/custom/src/views/fields/appuntamento-parent.js"
    "client/custom/src/views/appuntamento/record/edit-small.js"
  )

  echo "=== Backup ${backup} ==="
  backup_files "${backup}" "${files[@]}"

  echo "=== Download (Prospect + client) ==="
  for rel in \
    "custom/Espo/Custom/Services/Prospect.php" \
    "client/custom/src/helpers/appuntamento-prospect-sync.js" \
    "client/custom/src/views/fields/appuntamento-parent.js" \
    "client/custom/src/views/appuntamento/record/edit-small.js"
  do
    download "${branch_invito}" "${rel}"
  done

  echo "=== Download (GlobalLogic + entityDefs) ==="
  for rel in \
    "custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php" \
    "custom/Espo/Custom/Services/LeadProspectSync.php" \
    "custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json"
  do
    download "${branch_cal}" "${rel}"
  done

  grep -q "syncBrandPartnerFromSource" "${CRM_ROOT}/custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php"
  grep -q "fornitorePartner" "${CRM_ROOT}/client/custom/src/helpers/appuntamento-prospect-sync.js"
  echo "Verifica: calendario → nuovo appuntamento con Prospect → fornitore e brand compilati"
}

phase_d_provvigioni() {
  echo ""
  echo "========== D) Contratto — regole provvigioni (subpanel) =========="
  local backup="${CRM_ROOT}/backup/provvigione-allinea/server-${STAMP}"
  local branch="cursor/provvigione-regole-contratto-9999"

  local files=(
    "custom/Espo/Custom/Services/ProvvigioneManager.php"
    "custom/Espo/Custom/Services/RegolaProvvigionaleCalculator.php"
    "custom/Espo/Custom/Hooks/Provvigione/AccrualAndAmount.php"
    "custom/Espo/Custom/Hooks/Provvigione/AfterSaveContratto.php"
    "custom/Espo/Custom/Hooks/Quote/BeforeSave.php"
    "custom/Espo/Custom/Hooks/Quote/AfterSaveTotaleProvvigioni.php"
    "custom/Espo/Custom/Hooks/Quote/ProvvigioneConsolidata.php"
    "custom/Espo/Custom/Actions/Quote/RicalcolaProvvigioni.php"
    "custom/Espo/Custom/Controllers/Quote.php"
    "custom/Espo/Custom/Resources/metadata/app/actions.json"
    "custom/Espo/Custom/Resources/metadata/entityDefs/Provvigione.json"
    "custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json"
    "custom/Espo/Custom/Resources/metadata/formula/Quote.json"
    "custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json"
    "custom/Espo/Custom/Resources/metadata/clientDefs/Provvigione.json"
    "custom/Espo/Custom/Resources/layouts/Quote/detail.json"
    "custom/Espo/Custom/Resources/layouts/Quote/detailBottom.json"
    "custom/Espo/Custom/Resources/layouts/Quote/relationships/provvigioni.json"
    "custom/Espo/Custom/Resources/layouts/Provvigione/detail.json"
    "custom/Espo/Custom/Resources/layouts/Provvigione/list.json"
    "custom/Espo/Custom/Resources/layouts/Provvigione/listSmall.json"
    "custom/Espo/Custom/Resources/i18n/it_IT/Provvigione.json"
    "client/custom/src/views/provvigione/record/detail.js"
    "client/custom/src/views/provvigione/record/edit.js"
    "client/custom/src/handlers/quote/ricalcola-provvigioni.js"
    "database/2026-07-04-provvigione-base-calcolo.sql"
  )

  echo "=== Backup ${backup} ==="
  backup_files "${backup}" "${files[@]}"

  for legacy in \
    "${CRM_ROOT}/custom/Espo/Custom/Hooks/Provvigione/BeforeSaveLegacy.php" \
    "${CRM_ROOT}/client/custom/src/views/quote/record/detail.js"
  do
    if [[ -f "${legacy}" ]]; then
      rel="${legacy#${CRM_ROOT}/}"
      backup_files "${backup}" "${rel}"
      rm -f "${legacy}"
      echo "  RIMOSSO ${rel}"
    fi
  done

  echo "=== Download ==="
  for rel in "${files[@]}"; do
    download "${branch}" "${rel}" || echo "  SKIP ${rel}"
  done

  echo "Verifica: Contratto senza pannello 'Provvigioni (calcolo)'"
  echo "          Subpanel Provvigioni + pulsante Ricalcola provvigioni"
  echo "          totaleProvvigioni = somma importoConsolidato"
}

finish() {
  echo ""
  echo "=== Cache + rebuild ==="
  php clear_cache.php
  php rebuild.php
  rm -rf data/cache/*
  echo ""
  echo "Fatto. Browser: Ctrl+Shift+R (finestra anonima consigliata)."
}

echo "=============================================="
echo " Allineamento post 2 luglio — fase: ${PHASE}"
echo " Root: ${CRM_ROOT}"
echo "=============================================="

case "${PHASE}" in
  A|a) phase_a_kpi; finish ;;
  B|b) phase_b_sottostato; finish ;;
  C|c) phase_c_prospect_brand; finish ;;
  D|d) phase_d_provvigioni; finish ;;
  ALL|all|*)
    phase_b_sottostato
    phase_c_prospect_brand
    phase_a_kpi
    phase_d_provvigioni
    finish
    ;;
esac
