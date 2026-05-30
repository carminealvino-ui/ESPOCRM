#!/usr/bin/env bash
# Backup layout Quote + metadata critici PRIMA di deploy contratto.
# Non salva i dati DB (cliente/referente): per quelli serve backup MySQL o export record.
#
#   bash tools/backup-contratto-prima-deploy.sh
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

cd "${CRM_ROOT}" || exit 1

bash "${SCRIPT_DIR}/backup-quote-layouts.sh"

STAMP="$(ls -1t "${CRM_ROOT}/custom/backup-layouts" 2>/dev/null | head -1)"
DEST="${CRM_ROOT}/custom/backup-layouts/${STAMP}"

EXTRA=(
  "custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json"
  "custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json"
  "custom/Espo/Custom/Resources/metadata/formula/Quote.json"
  "custom/Espo/Custom/Actions/Opportunity/CreateContratto.php"
)

for rel in "${EXTRA[@]}"; do
  src="${CRM_ROOT}/${rel}"
  if [[ -f "${src}" ]]; then
    mkdir -p "${DEST}/$(dirname "${rel}")"
    cp -a "${src}" "${DEST}/${rel}"
    echo "${rel}" >> "${DEST}/files-extra.txt"
  fi
done

echo "Backup contratto completo (layout + metadata): ${DEST}"
echo "NOTA: Cliente/Contraente/Contatto sono DATI nel DB — non inclusi in questo backup."
