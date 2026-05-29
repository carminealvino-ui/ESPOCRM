#!/usr/bin/env bash
# Applica SOLO detail.json con pannello «Prezzi e Minus/Plus» (non tocca side panel / bottom total).
#
#   cd ~/public_html/crm/mec-group
#   bash tools/apply-quote-detail-prezzi-sample.sh
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/quote-prezzi-iva-inclusa-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SAMPLE="${SCRIPT_DIR}/layouts-samples/Quote/detail-prezzi-minusplus.json"
DEST="${CRM_ROOT}/custom/Espo/Custom/Resources/layouts/Quote/detail.json"

if [[ ! -f "${SAMPLE}" ]]; then
  mkdir -p "$(dirname "${SAMPLE}")"
  curl -fsSL "${BASE}/tools/layouts-samples/Quote/detail-prezzi-minusplus.json?t=$(date +%s)" -o "${SAMPLE}"
fi

if [[ ! -f "${SAMPLE}" ]]; then
  echo "ERRORE: sample mancante. Esegui: curl ... | bash  (bootstrap-server-tools.sh)"
  exit 1
fi

if [[ -x "${SCRIPT_DIR}/backup-quote-layouts.sh" ]]; then
  bash "${SCRIPT_DIR}/backup-quote-layouts.sh"
elif [[ -f "${CRM_ROOT}/tools/backup-quote-layouts.sh" ]]; then
  bash "${CRM_ROOT}/tools/backup-quote-layouts.sh"
fi

cp -f "${SAMPLE}" "${DEST}"
cd "${CRM_ROOT}"
php command.php rebuild
rm -rf data/cache/*
echo "OK: detail.json con pannello Prezzi e Minus/Plus."
echo "Side panel / totali in fondo: rifare in Layout Manager se servono."
