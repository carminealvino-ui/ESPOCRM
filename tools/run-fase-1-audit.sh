#!/usr/bin/env bash
# Fase 1 — audit listino (sola lettura)
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${GITHUB_BRANCH:-cursor/opportunity-globallogic-9999}"
REPO="${GITHUB_REPOSITORY:-carminealvino-ui/ESPOCRM}"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"

cd "$CRM_ROOT"
mkdir -p tools database

curl -fsSL "${BASE}/tools/fase-1-audit-listino.php" -o tools/fase-1-audit-listino.php
curl -fsSL "${BASE}/database/2026-05-27-fase-1-audit-solo-lettura.sql" \
  -o database/2026-05-27-fase-1-audit-solo-lettura.sql

echo "=== Audit PHP (consigliato) ==="
php tools/fase-1-audit-listino.php

echo ""
echo "=== Oppure SQL su MySQL ==="
echo "mysql -u USER -p DBNAME < database/2026-05-27-fase-1-audit-solo-lettura.sql"
