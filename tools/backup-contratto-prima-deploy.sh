#!/usr/bin/env bash
# Alias: backup layout Contratto (Quote) in backup_dev/ prima del deploy.
#   bash tools/backup-contratto-prima-deploy.sh
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
exec bash "${CRM_ROOT}/tools/backup-quote-layouts.sh"
