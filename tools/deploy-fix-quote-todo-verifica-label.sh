#!/usr/bin/env bash
# Fix label "To-Do verifica installazione" ripetuta sul dettaglio Contratto (Quote).
#
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-quote-todo-verifica-label-9999/tools/deploy-fix-quote-todo-verifica-label.sh" \
#     -o /tmp/deploy-todo-label.sh && bash /tmp/deploy-todo-label.sh

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/fix-quote-todo-verifica-label-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

echo "=== Fix To-Do verifica installazione label → ${CRM_ROOT} ==="

mkdir -p "${CRM_ROOT}/tools"
curl -fsSL -o "${CRM_ROOT}/tools/fix-quote-todo-verifica-label.php" \
  "${BASE}/tools/fix-quote-todo-verifica-label.php?t=$(date +%s)"

echo ">> dry-run"
php "${CRM_ROOT}/tools/fix-quote-todo-verifica-label.php" --dry-run || true

echo ">> apply"
php "${CRM_ROOT}/tools/fix-quote-todo-verifica-label.php"

# Conteggio anti-regressione
OCC="$(python3 - <<'PY' "${CRM_ROOT}/custom/Espo/Custom/Resources/layouts/Quote/detail.json"
import json,sys
p=sys.argv[1]
with open(p) as f: data=json.load(f)
n=0
for panel in data:
  for row in panel.get('rows',[]):
    if not isinstance(row,list):
      continue
    for cell in row:
      if isinstance(cell,dict) and cell.get('name')=='verificaInstallazioneTask':
        n+=1
print(n)
PY
)"

if [[ "${OCC}" != "1" ]]; then
  echo "ERRORE: occorrenze layout=${OCC} (atteso 1)" >&2
  exit 1
fi

LABEL_COUNT="$(python3 - <<'PY' "${CRM_ROOT}/custom/Espo/Custom/Resources/i18n/it_IT/Quote.json"
import json,sys
p=sys.argv[1]
with open(p) as f: data=json.load(f)
fields=data.get('fields',{})
n=sum(1 for k,v in fields.items() if v=='To-Do verifica installazione')
print(n)
print('keys=', [k for k,v in fields.items() if v=='To-Do verifica installazione'])
PY
)"

echo "i18n label count/info: ${LABEL_COUNT}"

if [[ -f "${CRM_ROOT}/command.php" ]]; then
  (cd "${CRM_ROOT}" && php command.php rebuild && php command.php clearCache)
elif [[ -f "${CRM_ROOT}/rebuild.php" ]]; then
  (cd "${CRM_ROOT}" && php rebuild.php && php clear_cache.php) || true
fi

echo ""
echo "Deploy OK. Hard refresh sul Contratto: deve restare UNA sola 'To-Do verifica installazione'."
