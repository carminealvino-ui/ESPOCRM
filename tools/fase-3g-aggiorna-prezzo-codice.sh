#!/usr/bin/env bash
# =============================================================================
# Re-imposta listPrice + prezzoCodice su prodotti già creati (da codice listino).
# Non crea nuovi prodotti.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL ".../tools/fase-3g-aggiorna-prezzo-codice.sh?t=$(date +%s)" -o /tmp/fase-3g.sh
#   DRY_RUN=1 bash /tmp/fase-3g.sh
#   bash /tmp/fase-3g.sh
# =============================================================================
set -euo pipefail

export NO_CREATE=1
export DRY_RUN="${DRY_RUN:-0}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" 2>/dev/null && pwd || true)"

if [[ -f "${SCRIPT_DIR}/fase-3f-import-listino-completo-pdf.sh" ]]; then
  bash "${SCRIPT_DIR}/fase-3f-import-listino-completo-pdf.sh"
else
  curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/opportunity-globallogic-9999/tools/fase-3f-import-listino-completo-pdf.sh?t=$(date +%s)" | bash
fi
