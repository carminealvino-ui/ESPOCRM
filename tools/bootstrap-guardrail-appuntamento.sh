#!/usr/bin/env bash
# Scarica su server i guardrail Appuntamento (verify + pre-deploy + deploy stati/esito).
# Usare finché non sono su main dopo merge PR #155.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/regola-repo-allineato-9999/tools/bootstrap-guardrail-appuntamento.sh?t=$(date +%s)" \
#     -o tools/bootstrap-guardrail-appuntamento.sh
#   bash tools/bootstrap-guardrail-appuntamento.sh
#
# Poi:
#   php tools/verify-appuntamento-baseline.php
#   bash tools/pre-deploy-check-appuntamento.sh

set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/regola-repo-allineato-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}" || exit 1
mkdir -p tools tools/backup-manifests REGOLE-PRODUZIONE

FILES=(
  "tools/verify-appuntamento-baseline.php"
  "tools/pre-deploy-check-appuntamento.sh"
  "tools/deploy-appuntamento-stati-esito-ui.sh"
  "tools/backup-manifests/appuntamento-baseline-produzione.files"
  "tools/DEPLOY-VIETATI.md"
  "REGOLE-PRODUZIONE/12-NO-DEPLOY-SENZA-REPO-ALLINEATO.md"
  "REGOLE-PRODUZIONE/13-FILE-CONDIVISI-VIETATO-CURL-INTERO.md"
)

echo "=== Bootstrap guardrail Appuntamento (branch ${BRANCH}) ==="

for rel in "${FILES[@]}"; do
  dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=$(date +%s)" -o "${dest}"
  [[ "${rel}" == *.sh ]] && chmod +x "${dest}"
  echo "OK ${rel}"
done

echo ""
echo "=== Verifica produzione ==="
php tools/verify-appuntamento-baseline.php || true
bash tools/pre-deploy-check-appuntamento.sh || true

echo ""
echo "Se verify fallisce → bash tools/deploy-appuntamento-stati-esito-ui.sh"
echo "Poi rifare export-delta e push main."
