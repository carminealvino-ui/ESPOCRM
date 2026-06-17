#!/usr/bin/env bash
# Esporta layout Contratto (Quote) da produzione per allineamento repo.
#
#   cd ~/public_html/crm/mec-group
#   bash tools/export-quote-layouts-for-repo.sh
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
LAYOUT_SRC="${CRM_ROOT}/custom/Espo/Custom/Resources/layouts/Quote"
STAMP="$(date +%Y%m%d-%H%M%S)"
EXPORT_DIR="${CRM_ROOT}/exports/sync/quote-layouts-${STAMP}"

if [[ ! -d "${LAYOUT_SRC}" ]]; then
  echo "ERRORE: layout Quote non trovato in ${LAYOUT_SRC}"
  exit 1
fi

mkdir -p "${EXPORT_DIR}"
cp -a "${LAYOUT_SRC}/." "${EXPORT_DIR}/"

MANIFEST="${EXPORT_DIR}/manifest.json"
{
  echo "{"
  echo "  \"version\": \"1.0.0\","
  echo "  \"generatedAt\": \"$(date -Iseconds)\","
  echo "  \"entity\": \"Quote\","
  echo "  \"layoutDir\": \"custom/Espo/Custom/Resources/layouts/Quote\","
  echo "  \"files\": ["
  first=1
  for f in "${EXPORT_DIR}"/*.json; do
    [[ -f "$f" ]] || continue
    base="$(basename "$f")"
    [[ "${base}" == "manifest.json" ]] && continue
    [[ $first -eq 0 ]] && echo ","
    first=0
    printf '    "%s"' "${base}"
  done
  echo ""
  echo "  ]"
  echo "}"
} > "${MANIFEST}"

ZIP_PATH="${CRM_ROOT}/exports/sync/quote-layouts-${STAMP}.zip"
if command -v zip >/dev/null 2>&1; then
  (cd "${EXPORT_DIR}" && zip -qr "${ZIP_PATH}" .)
  echo "ZIP: ${ZIP_PATH}"
fi

echo "Export layout Quote completato."
echo "Cartella: ${EXPORT_DIR}"
echo ""
echo "Prossimo passo (cPanel, push su GitHub):"
echo "  bash tools/align-quote-layouts-prod-repo.sh quote-layouts-${STAMP}"
