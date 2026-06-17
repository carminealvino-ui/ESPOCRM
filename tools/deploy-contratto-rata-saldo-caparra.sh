#!/usr/bin/env bash
# Contratto: caparra sempre visibile, rata prestito, importo saldo auto.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL --connect-timeout 30 --max-time 120 "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/contratto-rata-saldo-caparra-9999/tools/deploy-contratto-rata-saldo-caparra.sh?t=$(date +%s)" -o /tmp/deploy-contratto-rata-saldo.sh
#   bash /tmp/deploy-contratto-rata-saldo.sh
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/contratto-rata-saldo-caparra-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

CURL_OPTS=(
  -fsSL
  --connect-timeout 30
  --max-time 120
  --retry 4
  --retry-delay 5
  --retry-all-errors
)

cd "${CRM_ROOT}" || exit 1

bash "${CRM_ROOT}/tools/backup-quote-layouts.sh" 2>/dev/null || true

fetch() {
  local rel="$1"
  local dest="${CRM_ROOT}/${rel}"
  local url="${BASE}/${rel}?t=$(date +%s)"

  echo "→ Scarico ${rel} ..."
  mkdir -p "$(dirname "${dest}")"

  if ! curl "${CURL_OPTS[@]}" "${url}" -o "${dest}"; then
    echo "ERRORE: download fallito per ${rel}" >&2
    echo "URL: ${url}" >&2
    exit 1
  fi

  if [[ ! -s "${dest}" ]]; then
    echo "ERRORE: file vuoto ${dest}" >&2
    exit 1
  fi

  echo "OK ${rel}"
}

echo "=== Deploy Contratto: caparra, rata prestito, importo saldo ==="

for rel in \
  "custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json" \
  "custom/Espo/Custom/Resources/metadata/logicDefs/Quote.json" \
  "custom/Espo/Custom/Resources/metadata/formula/Quote.json" \
  "custom/Espo/Custom/Resources/layouts/Quote/detail.json" \
  "custom/Espo/Custom/Resources/i18n/it_IT/Quote.json" \
  "custom/Espo/Custom/Resources/i18n/en_US/Quote.json"
do
  fetch "${rel}"
done

echo "→ Rebuild metadata (può richiedere 1-3 minuti, attendere) ..."
php command.php rebuild
rm -rf data/cache/*
echo ""
echo "Fatto. Ctrl+Shift+R sul browser."
