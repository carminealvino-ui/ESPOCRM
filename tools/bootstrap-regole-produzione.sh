#!/usr/bin/env bash
# Crea REGOLE-PRODUZIONE/ in root CRM (documentazione operativa, branch main).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/main/tools/bootstrap-regole-produzione.sh?t=$(date +%s)" | bash
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-main}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}" || exit 1
mkdir -p REGOLE-PRODUZIONE

FILES=(
  "REGOLE-PRODUZIONE/README.md"
  "REGOLE-PRODUZIONE/00-ORDINE-DI-LAVORO.md"
  "REGOLE-PRODUZIONE/01-UN-ISTRUZIONE-ALLA-VOLTA.md"
  "REGOLE-PRODUZIONE/02-BACKUP-FIX-E-ROLLBACK.md"
  "REGOLE-PRODUZIONE/03-ISTRUZIONI-COMPLETE.md"
  "REGOLE-PRODUZIONE/COPIA-SUL-SERVER.md"
  "REGOLE-PRODUZIONE/04-STRUTTURA-BACKUP-DEV.md"
  "REGOLE-PRODUZIONE/05-SYNC-REPO-DAL-SERVER.md"
)

for rel in "${FILES[@]}"; do
  curl -fsSL "${BASE}/${rel}?t=$(date +%s)" -o "${CRM_ROOT}/${rel}"
  echo "OK ${rel}"
done

echo ""
ls -la "${CRM_ROOT}/REGOLE-PRODUZIONE/README.md"
