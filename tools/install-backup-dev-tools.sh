#!/usr/bin/env bash
# Installa backup-dev-batch.sh + manifest in tools/ (root CRM).
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/main/tools/install-backup-dev-tools.sh" | bash
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
GIT_ROOT="${GIT_ROOT:-$HOME/ESPOCRM-git}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/main"

mkdir -p "${CRM_ROOT}/tools/backup-manifests"

install_one() {
  local rel="$1"
  local dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  if [[ -f "${GIT_ROOT}/${rel}" ]]; then
    cp -a "${GIT_ROOT}/${rel}" "${dest}"
    echo "OK (git) ${rel}"
    return 0
  fi
  if curl -fsSL "${BASE}/${rel}?t=$(date +%s)" -o "${dest}"; then
    echo "OK (curl) ${rel}"
    return 0
  fi
  echo "ERRORE: non riesco a installare ${rel}"
  return 1
}

install_one "tools/backup-dev-batch.sh"
install_one "tools/backup-dev-save.sh"
install_one "tools/backup-manifests/google-sync.files"
install_one "tools/backup-manifests/prospect-form-ui.files"
install_one "tools/deploy-prospect-appuntamento-form-ui.sh"
chmod +x "${CRM_ROOT}/tools/backup-dev-batch.sh" "${CRM_ROOT}/tools/backup-dev-save.sh" 2>/dev/null || true

echo ""
ls -la "${CRM_ROOT}/tools/backup-dev-batch.sh" \
       "${CRM_ROOT}/tools/backup-dev-save.sh" \
       "${CRM_ROOT}/tools/backup-manifests/google-sync.files"
echo ""
echo "Poi: bash tools/backup-dev-batch.sh google-sync --manifest tools/backup-manifests/google-sync.files"
