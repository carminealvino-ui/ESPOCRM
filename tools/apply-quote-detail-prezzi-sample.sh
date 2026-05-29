#!/usr/bin/env bash
# Applica SOLO detail.json con pannello «Prezzi e Minus/Plus» (non tocca side panel / bottom total).
# Usa se il layout è stato semplificato dal deploy ma NON hai un backup completo.
#
#   cd ~/public_html/crm/mec-group
#   bash tools/apply-quote-detail-prezzi-sample.sh
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SAMPLE="${SCRIPT_DIR}/layouts-samples/Quote/detail-prezzi-minusplus.json"
DEST="${CRM_ROOT}/custom/Espo/Custom/Resources/layouts/Quote/detail.json"

if [[ ! -f "${SAMPLE}" ]]; then
  echo "ERRORE: sample mancante: ${SAMPLE}"
  exit 1
fi

bash "${SCRIPT_DIR}/backup-quote-layouts.sh"

cp -f "${SAMPLE}" "${DEST}"
cd "${CRM_ROOT}"
php command.php rebuild
rm -rf data/cache/*
echo "OK: detail.json con pannello Prezzi e Minus/Plus."
echo "Side panel / totali in fondo: rifare in Layout Manager se servono."
