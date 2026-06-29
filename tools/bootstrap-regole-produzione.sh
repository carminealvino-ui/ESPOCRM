#!/usr/bin/env bash
# Installa l'unico file regole su produzione.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/main/tools/bootstrap-regole-produzione.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-main}"
REPO="carminealvino-ui/ESPOCRM"
DEST="${CRM_ROOT}/REGOLE-PRODUZIONE/REGOLE.md"

mkdir -p "$(dirname "${DEST}")"
curl -fsSL "https://raw.githubusercontent.com/${REPO}/${BRANCH}/REGOLE-PRODUZIONE/REGOLE.md?t=$(date +%s)" -o "${DEST}"

echo "OK ${DEST}"
echo "Leggere solo REGOLE-PRODUZIONE/REGOLE.md (documento unico)."
