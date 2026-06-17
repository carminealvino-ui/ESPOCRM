#!/usr/bin/env bash
# Campi finanziamento su Contratto (Quote): caparra, importo finanziato, nr. rate, tasso zero.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/contratto-campi-finanziamento-9999/tools/deploy-contratto-campi-finanziamento.sh?t=$(date +%s)" -o /tmp/deploy-contratto-finanziamento.sh
#   bash /tmp/deploy-contratto-finanziamento.sh
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
DRY_RUN="${DRY_RUN:-0}"
BRANCH="${BRANCH:-cursor/contratto-campi-finanziamento-9999}"
REPO_RAW="${REPO_RAW:-https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" 2>/dev/null && pwd || true)"

log() { echo "[deploy-contratto-finanziamento] $*"; }

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
  log "Deploy campi finanziamento su Contratto (Quote)"

  fetch_or_local "custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json" \
    "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json"
  fetch_or_local "custom/Espo/Custom/Resources/metadata/logicDefs/Quote.json" \
    "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/logicDefs/Quote.json"
  fetch_or_local "custom/Espo/Custom/Resources/layouts/Quote/detail.json" \
    "${CRM_ROOT}/custom/Espo/Custom/Resources/layouts/Quote/detail.json"
  fetch_or_local "custom/Espo/Custom/Resources/i18n/it_IT/Quote.json" \
    "${CRM_ROOT}/custom/Espo/Custom/Resources/i18n/it_IT/Quote.json"
  fetch_or_local "custom/Espo/Custom/Resources/i18n/en_US/Quote.json" \
    "${CRM_ROOT}/custom/Espo/Custom/Resources/i18n/en_US/Quote.json"

  rebuild_crm

  log "Fatto. I campi compaiono solo se Finanziamento è attivo."
}

main "$@"
