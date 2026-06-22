#!/usr/bin/env bash
# Applica export layout Quote nel working tree Git (repo locale / cloud agent).
#
#   php tools/sync-custom-prod-repo.php apply-delta exports/sync/delta-...   # sync completo
#   bash tools/apply-quote-layouts-from-export.sh exports/sync/quote-layouts-YYYYMMDD-HHMMSS
#
# Sul clone cPanel:
#   bash tools/apply-quote-layouts-from-export.sh /home/telcalli/public_html/crm/mec-group/exports/sync/quote-layouts-...
set -euo pipefail

EXPORT_PATH="${1:-}"
REPO_ROOT="${REPO_ROOT:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
LAYOUT_DEST="${REPO_ROOT}/custom/Espo/Custom/Resources/layouts/Quote"

if [[ -z "${EXPORT_PATH}" ]]; then
  echo "Usage: bash tools/apply-quote-layouts-from-export.sh exports/sync/quote-layouts-YYYYMMDD-HHMMSS"
  exit 1
fi

if [[ ! -d "${EXPORT_PATH}" ]]; then
  echo "ERRORE: cartella export non trovata: ${EXPORT_PATH}"
  exit 1
fi

shopt -s nullglob
json_files=("${EXPORT_PATH}"/*.json)
if [[ ${#json_files[@]} -eq 0 ]]; then
  echo "ERRORE: nessun file .json in ${EXPORT_PATH}"
  exit 1
fi

mkdir -p "${LAYOUT_DEST}"
BACKUP_DIR="${REPO_ROOT}/backup_dev/Quote/layouts-snapshots/repo-before-$(date +%Y%m%d-%H%M%S)"
mkdir -p "${BACKUP_DIR}"
cp -a "${LAYOUT_DEST}/." "${BACKUP_DIR}/" 2>/dev/null || true
echo "Backup repo: ${BACKUP_DIR}"

for f in "${json_files[@]}"; do
  base="$(basename "$f")"
  [[ "${base}" == "manifest.json" ]] && continue
  cp -f "$f" "${LAYOUT_DEST}/${base}"
  echo "OK ${base}"
done

echo ""
echo "Layout Quote applicati in ${LAYOUT_DEST}"
echo "Verifica: git diff custom/Espo/Custom/Resources/layouts/Quote/"
echo "Poi: git add ... && git commit && git push"
