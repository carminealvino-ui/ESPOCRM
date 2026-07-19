#!/usr/bin/env bash
# P0 passo 1 — Guardrails: backup obbligatorio + rimuove BeforeSaveLegacy.
#
# Un tema solo (niente totaleProvvigioni / naming Lead in questo script).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/p0-deploy-guardrails-9999/tools/deploy-p0-guardrails.sh?t=$(date +%s)" | bash
#
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/p0-deploy-guardrails-9999}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${BRANCH}"
STAMP="$(date +%Y%m%d-%H%M%S)"
LEGACY="custom/Espo/Custom/Hooks/Provvigione/BeforeSaveLegacy.php"
DOC="tools/DEPLOY-SOLO-DA-MAIN.md"

cd "${CRM_ROOT}"

echo "=== P0.1 Guardrails — BRANCH=${BRANCH} ==="
echo "CRM_ROOT=${CRM_ROOT}"

# --- PASSO 0: BACKUP OBBLIGATORIO ---
echo ""
echo "=== PASSO 0 — Backup in backup_dev/ ==="
mkdir -p backup_dev/_sessions tools/backup-manifests

if [[ -f "${LEGACY}" ]]; then
  if [[ -f tools/backup-dev-save.sh ]]; then
    bash tools/backup-dev-save.sh Provvigione p0-guardrails hooks BeforeSaveLegacy.php
  else
    DEST="backup_dev/Provvigione/hooks"
    mkdir -p "${DEST}"
    cp -a "${LEGACY}" "${DEST}/${STAMP}_p0-guardrails_hooks_BeforeSaveLegacy.php"
    echo "Backup: ${DEST}/${STAMP}_p0-guardrails_hooks_BeforeSaveLegacy.php"
  fi
else
  echo "INFO: ${LEGACY} già assente — nessun file da backupare (ok)."
fi

# Log sessione
SESSION="backup_dev/_sessions/${STAMP}_p0-guardrails"
mkdir -p "${SESSION}"
{
  echo "stamp=${STAMP}"
  echo "branch=${BRANCH}"
  echo "legacy_existed_before=$([ -f "${LEGACY}" ] && echo yes || echo no)"
  ls -la "backup_dev/Provvigione/hooks/" 2>/dev/null | tail -5 || true
} > "${SESSION}/log.txt"
echo "Sessione: ${SESSION}/log.txt"

echo ""
echo "=== Verifica backup (obbligatoria) ==="
ls -la backup_dev/_sessions/ | tail -5
ls -lt backup_dev/Provvigione/hooks/ 2>/dev/null | head -5 || echo "(cartella hooks ancora vuota se file già assente)"

# --- PASSO 1: rimozione hook legacy ---
echo ""
echo "=== PASSO 1 — Rimuove BeforeSaveLegacy ==="
if [[ -f "${LEGACY}" ]]; then
  rm -f "${LEGACY}"
  echo "RIMOSSO ${LEGACY}"
else
  echo "OK già assente: ${LEGACY}"
fi

# Doc regole deploy (utile sul server)
mkdir -p tools
curl -fsSL "${BASE}/${DOC}?t=${STAMP}" -o "${DOC}"
echo "OK ${DOC}"

# --- Rebuild ---
echo ""
echo "=== Clear cache + rebuild ==="
php clear_cache.php
php rebuild.php

echo ""
echo "=== FATTO P0 guardrails ==="
echo "Verifica UI: salva una Provvigione (non deve dare 500)."
echo "Solo dopo OK → passo successivo: fix totaleProvvigioni (#123)."
echo ""
echo "Rollback (se serve):"
echo "  cp backup_dev/Provvigione/hooks/*_BeforeSaveLegacy.php ${LEGACY}"
echo "  php clear_cache.php && php rebuild.php"
