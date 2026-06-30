#!/usr/bin/env bash
# DEPRECATO: rollback disponibilità calendario.
# Usare deploy-rollback-disponibilita-calendario.sh
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-disponibilita-calendario-colore-9999/tools/deploy-rollback-disponibilita-calendario.sh?t=$(date +%s)" | bash

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
exec "${SCRIPT_DIR}/deploy-rollback-disponibilita-calendario.sh" "$@"
