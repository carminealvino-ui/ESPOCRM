#!/usr/bin/env bash
# Rollback file calendario Disponibilità (richiede backup Passo 0).
# Per ripristino completo usare deploy-ripristina-disponibilita-calendario.sh
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-disponibilita-calendario-colore-9999/tools/deploy-rollback-disponibilita-calendario.sh?t=$(date +%s)" | bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/fix-disponibilita-calendario-colore-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
FIX_TAG="disponibilita-calendario"

has_backup() {
  local sessions="${CRM_ROOT}/backup_dev/_sessions"
  [[ -d "${sessions}" ]] || return 1
  local latest
  latest="$(find "${sessions}" -maxdepth 1 -type d -name "*_${FIX_TAG}" 2>/dev/null | sort -r | head -1)"
  [[ -n "${latest}" && -f "${latest}/manifest.txt" ]]
}

if [[ "${SKIP_BACKUP_CHECK:-}" != "1" ]] && ! has_backup; then
  echo "PASSO 0 — eseguire prima il backup (vedi deploy-ripristina-disponibilita-calendario.sh)"
  exit 1
fi

CRM_ROOT="${CRM_ROOT}" BRANCH="${BRANCH}" exec "${SCRIPT_DIR}/deploy-ripristina-disponibilita-calendario.sh" "${CRM_ROOT}" "${BRANCH}"
