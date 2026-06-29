#!/usr/bin/env bash
# Rimuove solo file backup morti (whitelist §9 — approvato «OK deploy cleanup backup»).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/fix-cleanup-backup-files-9999/tools/deploy-cleanup-backup-files.sh?t=$(date +%s)" | bash

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"

FILES=(
  "custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php.bak-20260527"
  "custom/Espo/Custom/Actions/Opportunity/CreateContratto_1.1.0_BACKUP.php"
)

echo "=== Cleanup backup (solo whitelist) → ${CRM_ROOT} ==="

removed=0
for rel in "${FILES[@]}"; do
  target="${CRM_ROOT}/${rel}"
  if [[ -f "${target}" ]]; then
    rm -f "${target}"
    echo "RIMOSSO ${rel}"
    removed=$((removed + 1))
  else
    echo "ASSENTE ${rel} (già ok)"
  fi
done

echo ""
echo "File rimossi: ${removed}/${#FILES[@]}"

if [[ -f "${CRM_ROOT}/command.php" ]]; then
  (cd "${CRM_ROOT}" && php command.php clearCache)
fi

echo "Fatto. Nessun hook o action attivo toccato."
