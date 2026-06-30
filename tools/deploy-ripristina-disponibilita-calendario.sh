#!/usr/bin/env bash
# Ripristino Disponibilità nel calendario (con Passo 0 backup obbligatorio).
#
# Passo 0 (obbligatorio):
#   cd ~/public_html/crm/mec-group
#   bash tools/backup-dev-batch.sh disponibilita-calendario \
#     --manifest tools/backup-manifests/disponibilita-calendario.files
#
# Deploy:
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-disponibilita-calendario-colore-9999/tools/deploy-ripristina-disponibilita-calendario.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
BRANCH="${2:-cursor/fix-disponibilita-calendario-colore-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
FIX_TAG="disponibilita-calendario"

FILES=(
  "custom/Espo/Custom/Hooks/Disponibilita/SetName.php"
  "custom/Espo/Custom/Resources/metadata/clientDefs/Calendar.json"
  "client/custom/src/views/calendar/calendar.js"
  "tools/data/brand-calendar-colors.json"
  "tools/disponibilita-date-helpers.php"
  "tools/diagnose-disponibilita-calendario.php"
  "tools/ripristina-disponibilita-record-calendario.php"
  "tools/backup-manifests/disponibilita-calendario.files"
)

has_backup() {
  local sessions="${CRM_ROOT}/backup_dev/_sessions"
  [[ -d "${sessions}" ]] || return 1
  local latest
  latest="$(find "${sessions}" -maxdepth 1 -type d -name "*_${FIX_TAG}" 2>/dev/null | sort -r | head -1)"
  [[ -n "${latest}" && -f "${latest}/manifest.txt" && -f "${latest}/files.list" ]]
}

echo "=== Ripristino Disponibilità calendario → ${CRM_ROOT} ==="

if [[ "${SKIP_BACKUP_CHECK:-}" != "1" ]] && ! has_backup; then
  echo ""
  echo "PASSO 0 — backup obbligatorio (REGOLE-PRODUZIONE):"
  echo "  cd ${CRM_ROOT}"
  echo "  curl -fsSL \"${BASE}/tools/backup-dev-batch.sh?t=\$(date +%s)\" -o tools/backup-dev-batch.sh"
  echo "  curl -fsSL \"${BASE}/tools/backup-manifests/disponibilita-calendario.files?t=\$(date +%s)\" \\"
  echo "    -o tools/backup-manifests/disponibilita-calendario.files"
  echo "  bash tools/backup-dev-batch.sh ${FIX_TAG} --manifest tools/backup-manifests/disponibilita-calendario.files"
  echo ""
  echo "Poi rilancia questo script. Per forzare senza backup: SKIP_BACKUP_CHECK=1"
  exit 1
fi

if has_backup; then
  latest="$(find "${CRM_ROOT}/backup_dev/_sessions" -maxdepth 1 -type d -name "*_${FIX_TAG}" 2>/dev/null | sort -r | head -1)"
  echo "Backup trovato: ${latest#${CRM_ROOT}/}"
fi

for rel in "${FILES[@]}"; do
  target="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${target}")"
  curl -fsSL -o "${target}" "${BASE}/${rel}?t=$(date +%s)"
  echo "OK ${rel}"
done

deploy_client_file() {
  local rel="$1"
  local tmp="$2"
  local suffix="${rel#client/custom/}"
  local target

  for target in \
    "${CRM_ROOT}/${rel}" \
    "${CRM_ROOT}/custom/Espo/Custom/Resources/client/custom/${suffix}" \
    "${CRM_ROOT}/custom/Espo/Custom/client/custom/${suffix}"; do
    mkdir -p "$(dirname "${target}")"
    cp "${tmp}" "${target}"
    echo "OK ${target#${CRM_ROOT}/}"
  done
}

TMP="$(mktemp)"
curl -fsSL -o "${TMP}" "${BASE}/client/custom/src/views/calendar/calendar.js?t=$(date +%s)"
deploy_client_file "client/custom/src/views/calendar/calendar.js" "${TMP}"
rm -f "${TMP}"

rm -f "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/app/calendar.json"
echo "OK rimosso metadata/app/calendar.json (se presente)"

for path in \
  "${CRM_ROOT}/client/custom/src/views/calendar/calendar.js" \
  "${CRM_ROOT}/custom/Espo/Custom/Resources/client/custom/src/views/calendar/calendar.js" \
  "${CRM_ROOT}/custom/Espo/Custom/client/custom/src/views/calendar/calendar.js"; do
  if grep -q "buildDisponibilitaEvents" "${path}" 2>/dev/null; then
    echo "ERRORE: ${path} ha ancora logica custom disponibilità" >&2
    exit 1
  fi
done

grep -q '"Disponibilita"' "${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/clientDefs/Calendar.json" || {
  echo "ERRORE: Disponibilita non in Calendar.json scopeList" >&2
  exit 1
}

(cd "${CRM_ROOT}" && php command.php rebuild && php command.php clearCache)

echo ""
echo "Diagnostica..."
(cd "${CRM_ROOT}" && php tools/diagnose-disponibilita-calendario.php --from=2026-06-29 --to=2026-07-05)

echo ""
echo "Ripristino record settimana (hook SetName, no SQL diretto)..."
(cd "${CRM_ROOT}" && php tools/ripristina-disponibilita-record-calendario.php --from=2026-06-29 --to=2026-07-05)

echo ""
echo "Fatto. Ctrl+Shift+R sul calendario."
echo "Se ancora non vedi le barre ARIEL, incolla l'output diagnostica sopra."
