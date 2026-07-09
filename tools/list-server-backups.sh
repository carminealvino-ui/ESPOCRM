#!/usr/bin/env bash
# Elenca backup locali sul server CRM (creati dagli script deploy).
#
# Uso:
#   bash tools/list-server-backups.sh
#   CRM_ROOT=/path/to/crm bash tools/list-server-backups.sh

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"

cd "${CRM_ROOT}"

echo "=== Backup in ${CRM_ROOT}/backup ==="
if [[ -d backup ]]; then
  find backup -type f \( -name '*.bak' -o -name '*.bak-*' -o -name 'Appuntamento.php*' -o -name 'Opportunity.php*' \) \
    -printf '%T@ %TY-%Tm-%Td %TH:%TM  %p\n' 2>/dev/null \
    | sort -rn \
    | head -40 \
    | cut -d' ' -f2-
else
  echo "(cartella backup assente)"
fi

echo ""
echo "=== Snapshot KPI v2 (deploy-crm-kpi-v2-hotfix) ==="
if [[ -d backup/crm-kpi-dashlet-v2 ]]; then
  ls -lt backup/crm-kpi-dashlet-v2 2>/dev/null | head -15
else
  echo "(nessuno)"
fi

echo ""
echo "=== File attuale Appuntamento.php (prime righe rilevanti) ==="
if [[ -f custom/Espo/Custom/Controllers/Appuntamento.php ]]; then
  rg -n "getContainer|injectableFactory|class Appuntamento|getActionGetSummary" \
    custom/Espo/Custom/Controllers/Appuntamento.php || true
else
  echo "(file assente)"
fi

echo ""
echo "=== Backup congelato nel repo Git (sempre disponibile) ==="
echo "  backup/stato-noto-buono-2026-07-09/"
echo "  bash tools/restore-da-backup-repo.sh"
echo ""
echo "=== Branch / script curl ==="
echo "  cursor/fix-contratto-stato-provvigioni-9999"
echo "  bash tools/restore-stato-noto-buono.sh"
