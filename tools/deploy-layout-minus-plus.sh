#!/usr/bin/env bash
# =============================================================================
# Layout UI: Minus/Plus (minusbonus), prezzo codice, importo — Quote + Opportunity
#
# Uso in produzione:
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/provvigioni-manuali-fase-a-9999/tools/deploy-layout-minus-plus.sh?t=$(date +%s)" -o /tmp/deploy-layout-minus-plus.sh
#   bash /tmp/deploy-layout-minus-plus.sh
#
# Solo anteprima (non scrive file):
#   DRY_RUN=1 bash /tmp/deploy-layout-minus-plus.sh
#
# Da repo locale:
#   CRM_ROOT=~/public_html/crm/mec-group bash tools/deploy-layout-minus-plus.sh
# =============================================================================
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
DRY_RUN="${DRY_RUN:-0}"
BRANCH="${BRANCH:-cursor/provvigioni-manuali-fase-a-9999}"
REPO_RAW="${REPO_RAW:-https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" 2>/dev/null && pwd || true)"
LAYOUT_BASE="${CRM_ROOT}/custom/Espo/Custom/Resources/layouts"
QUOTE_DETAIL="${LAYOUT_BASE}/Quote/detail.json"
OPP_DETAIL="${LAYOUT_BASE}/Opportunity/detail.json"

log() { echo "[deploy-layout-minus-plus] $*"; }

need_cmd() {
  command -v "$1" >/dev/null 2>&1 || {
    echo "ERRORE: comando '$1' non trovato." >&2
    exit 1
  }
}

write_file() {
  local dest="$1"
  local src="$2"
  if [[ "${DRY_RUN}" == "1" ]]; then
    log "DRY_RUN: scriverei ${dest} (${#src} byte da ${src})"
    return 0
  fi
  mkdir -p "$(dirname "${dest}")"
  cp -f "${src}" "${dest}"
  log "OK ${dest}"
}

fetch_or_local() {
  local rel="$1"
  local dest="$2"
  local local_path="${SCRIPT_DIR}/../${rel}"

  if [[ -f "${local_path}" ]]; then
    write_file "${dest}" "${local_path}"
    return 0
  fi

  need_cmd curl
  local url="${REPO_RAW}/${rel}?t=$(date +%s)"
  if [[ "${DRY_RUN}" == "1" ]]; then
    log "DRY_RUN: curl ${url} → ${dest}"
    return 0
  fi
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${url}" -o "${dest}"
  log "OK scaricato ${rel}"
}

patch_opportunity_minus_plus() {
  need_cmd jq

  if [[ ! -f "${OPP_DETAIL}" ]]; then
    log "ATTENZIONE: ${OPP_DETAIL} assente; salto patch Opportunity."
    return 0
  fi

  local tmp
  tmp="$(mktemp)"

  # Pannello "Formulazione Opportunità": aggiunge importo + minusPlus se mancanti
  jq '
    map(
      if .customLabel == "Formulazione Opportunità" or .label == "Formulazione Opportunità" then
        .rows |= (
          if any(.[]; .name == "minusPlus") then .
          else . + [[
            {"name": "importoOpportunit", "customLabel": "Importo venduto (IVA incl. B2C)"},
            {"name": "minusPlus", "customLabel": "Minus / Plus (minusbonus €)"},
            false
          ]]
          end
        )
      else .
      end
    )
  ' "${OPP_DETAIL}" > "${tmp}"

  if [[ "${DRY_RUN}" == "1" ]]; then
    log "DRY_RUN: patch Opportunity minusPlus (anteprima prime righe aggiunte):"
    jq '.[] | select(.customLabel=="Formulazione Opportunità") | .rows[-2:]' "${tmp}" 2>/dev/null || true
    rm -f "${tmp}"
    return 0
  fi

  mv "${tmp}" "${OPP_DETAIL}"
  log "OK patch Opportunity: importoOpportunit + minusPlus"
}

rebuild_crm() {
  if [[ ! -d "${CRM_ROOT}" ]]; then
    log "ATTENZIONE: CRM_ROOT=${CRM_ROOT} non esiste; salto rebuild."
    return 0
  fi
  if [[ "${DRY_RUN}" == "1" ]]; then
    log "DRY_RUN: php command.php rebuild && rm -rf data/cache/*"
    return 0
  fi
  cd "${CRM_ROOT}"
  php command.php rebuild
  rm -rf data/cache/*
  log "OK rebuild + cache pulita"
}

main() {
  log "CRM_ROOT=${CRM_ROOT} BRANCH=${BRANCH}"

  # Contratto (Quote): pannello completo Provvigioni con minusPlus
  fetch_or_local "custom/Espo/Custom/Resources/layouts/Quote/detail.json" "${QUOTE_DETAIL}"

  # Opportunità: patch jq sul layout esistente (non sovrascrive tutto il file)
  if [[ -f "${OPP_DETAIL}" ]]; then
    patch_opportunity_minus_plus
  else
    fetch_or_local "custom/Espo/Custom/Resources/layouts/Opportunity/detail.json" "${OPP_DETAIL}"
    patch_opportunity_minus_plus
  fi

  rebuild_crm

  log "Fatto. Apri un Contratto: pannello «Provvigioni (calcolo)» → Minus / Plus venduto."
  log "Il valore si calcola al Salva (non editabile); serve importo + prezzo codice da prodotto/listino."
}

main "$@"
