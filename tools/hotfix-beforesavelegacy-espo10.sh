#!/usr/bin/env bash
# Hotfix urgente: BeforeSaveLegacy non deve più estendere Espo\Core\Hooks\Base (Espo 10).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/appuntamento-taxi-provvigione-9999/tools/hotfix-beforesavelegacy-espo10.sh?t=$(date +%s)" \
#     -o tools/hotfix-beforesavelegacy-espo10.sh
#   bash tools/hotfix-beforesavelegacy-espo10.sh

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/appuntamento-taxi-provvigione-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

FILE="custom/Espo/Custom/Hooks/Provvigione/BeforeSaveLegacy.php"

echo "=== Hotfix BeforeSaveLegacy Espo 10 → ${CRM_ROOT} ==="
cd "${CRM_ROOT}"

mkdir -p "$(dirname "${CRM_ROOT}/${FILE}")"
curl -fsSL -o "${CRM_ROOT}/${FILE}" "${BASE}/${FILE}?t=$(date +%s)"
echo "OK ${FILE}"

if grep -q 'Espo\\Core\\Hooks\\Base' "${CRM_ROOT}/${FILE}"; then
  echo "ERRORE: file contiene ancora Hooks\\Base" >&2
  exit 1
fi

grep -q 'implements BeforeSave' "${CRM_ROOT}/${FILE}" || {
  echo "ERRORE: non implementa BeforeSave" >&2
  exit 1
}

if [[ -f "${CRM_ROOT}/clear_cache.php" ]]; then
  php clear_cache.php || true
fi

echo "=== Hotfix applicato — riprovare Salva Appuntamento ==="
