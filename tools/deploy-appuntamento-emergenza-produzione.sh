#!/usr/bin/env bash
# Alias → deploy-appuntamento-produzione.sh (stesso fix, nome storico).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/main/tools/deploy-appuntamento-emergenza-produzione.sh?t=$(date +%s)" | bash
#
# Rollback:
#   bash tools/rollback-produzione.sh
#   bash tools/rollback-produzione.sh 20260529-143000
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-main}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

exec bash -c "curl -fsSL \"${BASE}/tools/deploy-appuntamento-produzione.sh?t=\$(date +%s)\" | bash"
