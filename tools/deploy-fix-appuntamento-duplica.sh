#!/usr/bin/env bash
# Alias → deploy-appuntamento-produzione.sh
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/main/tools/deploy-fix-appuntamento-duplica.sh?t=$(date +%s)" | bash
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-main}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

exec bash -c "curl -fsSL \"${BASE}/tools/deploy-appuntamento-produzione.sh?t=\$(date +%s)\" | bash"
