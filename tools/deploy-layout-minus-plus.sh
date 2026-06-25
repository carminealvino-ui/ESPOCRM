#!/usr/bin/env bash
# =============================================================================
# ATTENZIONE: questo script SOVRASCRIVE detail.json Quote/Opportunity sul server.
# Preferire: backup-quote-layouts.sh + Layout Manager, oppure apply-quote-detail-prezzi-sample.sh
# I deploy prezzi sicuri sono deploy-contratto-prezzi-curl.sh (NON toccano layout).
# =============================================================================
# Layout Contratto (Quote): solo prezzi + Minus/Plus — NESSUN campo provvigioni
# Layout Opportunità: importo venduto + Minus/Plus
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/provvigioni-manuali-fase-a-9999/tools/deploy-layout-minus-plus.sh?t=$(date +%s)" -o /tmp/deploy-layout-contratto-prezzi.sh
#   bash /tmp/deploy-layout-contratto-prezzi.sh
# =============================================================================
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
DRY_RUN="${DRY_RUN:-0}"
BRANCH="${BRANCH:-cursor/provvigioni-manuali-fase-a-9999}"
REPO_RAW="${REPO_RAW:-https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" 2>/dev/null && pwd || true)"
LAYOUT_BASE="${CRM_ROOT}/custom/Espo/Custom/Resources/layouts"
CLIENT_DEFS="${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json"

log() { echo "[deploy-layout-contratto-prezzi] $*"; }

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
    log "DRY_RUN: scriverei ${dest} ← ${src}"
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

rebuild_crm() {
  if [[ ! -d "${CRM_ROOT}" ]] || [[ ! -f "${CRM_ROOT}/command.php" ]]; then
    log "ATTENZIONE: rebuild saltato (CRM_ROOT o command.php mancante)."
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
  log "CRM_ROOT=${CRM_ROOT}"
  log "Contratto: pannello «Prezzi e Minus/Plus» — senza provvigioni in layout/UI"

  fetch_or_local "custom/Espo/Custom/Resources/layouts/Quote/detail.json" "${LAYOUT_BASE}/Quote/detail.json"
  fetch_or_local "custom/Espo/Custom/Resources/layouts/Opportunity/detail.json" "${LAYOUT_BASE}/Opportunity/detail.json"
  fetch_or_local "custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json" "${CLIENT_DEFS}"

  rebuild_crm

  log "Fatto. Sul contratto: solo prezzi, codice, Minus/Plus."
  log "Provvigioni: gestione separata (non in questa scheda)."
}

main "$@"
