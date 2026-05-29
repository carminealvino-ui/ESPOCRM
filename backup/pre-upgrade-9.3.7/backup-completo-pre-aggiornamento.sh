#!/bin/bash
# =============================================================================
# BACKUP COMPLETO prima aggiornamento EspoCRM (es. 9.3.7)
# Eseguire dalla root CRM: ~/public_html/crm/mec-group
#
# Uso:
#   cd ~/public_html/crm/mec-group
#   bash backup/pre-upgrade-9.3.7/backup-completo-pre-aggiornamento.sh
#
# Output: backup/pre-upgrade-9.3.7/snapshots/YYYYMMDD-HHMM/
# =============================================================================

set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$(pwd)}"
TS="$(date +%Y%m%d-%H%M)"
SNAP_DIR="${CRM_ROOT}/backup/pre-upgrade-9.3.7/snapshots/${TS}"
CONFIG_PHP="${CRM_ROOT}/data/config-internal.php"

cd "${CRM_ROOT}"
mkdir -p "${SNAP_DIR}"

echo "=============================================="
echo " BACKUP PRE-AGGIORNAMENTO EspoCRM"
echo " Root: ${CRM_ROOT}"
echo " Snapshot: ${SNAP_DIR}"
echo " Data: $(date '+%Y-%m-%d %H:%M:%S')"
echo "=============================================="

# -----------------------------------------------------------------------------
# 1) Versione attuale (se disponibile)
# -----------------------------------------------------------------------------
echo ""
echo "==> 1) Versione installata"
if [[ -f "${CRM_ROOT}/application/Espo/Core/Utils/Config.php" ]]; then
  php -r "
    if (is_file('${CRM_ROOT}/data/config.php')) {
      \$c = include '${CRM_ROOT}/data/config.php';
      echo '  version (config): ' . (\$c['version'] ?? 'n/d') . PHP_EOL;
    }
  " 2>/dev/null || true
fi
if [[ -f "${CRM_ROOT}/data/cache/application/version.php" ]]; then
  grep -E "version|'version'" "${CRM_ROOT}/data/cache/application/version.php" 2>/dev/null | head -3 || true
fi
php command.php version 2>/dev/null | head -5 || echo "  (comando version non disponibile)"

# -----------------------------------------------------------------------------
# 2) Database
# -----------------------------------------------------------------------------
echo ""
echo "==> 2) Dump database"
if [[ ! -f "${CONFIG_PHP}" ]]; then
  echo "  ERRORE: manca ${CONFIG_PHP}"
  exit 1
fi

DB_INFO="$(php -r "
\$c = include '${CONFIG_PHP}';
echo (\$c['database']['host'] ?? 'localhost') . '|';
echo (\$c['database']['port'] ?? '3306') . '|';
echo (\$c['database']['dbname'] ?? '') . '|';
echo (\$c['database']['user'] ?? '') . '|';
echo (\$c['database']['password'] ?? '') . '|';
")"

IFS='|' read -r DB_HOST DB_PORT DB_NAME DB_USER DB_PASS <<< "${DB_INFO}"

if [[ -z "${DB_NAME}" || -z "${DB_USER}" ]]; then
  echo "  ERRORE: credenziali DB non lette da config-internal.php"
  exit 1
fi

DB_DUMP="${SNAP_DIR}/database-${DB_NAME}.sql.gz"
MYCNF="$(mktemp)"
chmod 600 "${MYCNF}"
cat > "${MYCNF}" <<EOF
[client]
host=${DB_HOST}
port=${DB_PORT}
user=${DB_USER}
password=${DB_PASS}
EOF

if command -v mariadb-dump >/dev/null 2>&1; then
  DUMP_CMD=mariadb-dump
elif command -v mysqldump >/dev/null 2>&1; then
  DUMP_CMD=mysqldump
else
  echo "  ERRORE: mariadb-dump / mysqldump non trovato"
  rm -f "${MYCNF}"
  exit 1
fi

"${DUMP_CMD}" --defaults-extra-file="${MYCNF}" \
  --single-transaction --routines --triggers \
  "${DB_NAME}" | gzip -9 > "${DB_DUMP}"
rm -f "${MYCNF}"

echo "  OK: ${DB_DUMP} ($(du -h "${DB_DUMP}" | cut -f1))"

# -----------------------------------------------------------------------------
# 3) Cartella custom (PHP + metadata)
# -----------------------------------------------------------------------------
echo ""
echo "==> 3) custom/"
if [[ -d "${CRM_ROOT}/custom" ]]; then
  tar -czf "${SNAP_DIR}/custom.tar.gz" -C "${CRM_ROOT}" custom
  echo "  OK: custom.tar.gz ($(du -h "${SNAP_DIR}/custom.tar.gz" | cut -f1))"
else
  echo "  ATTENZIONE: cartella custom assente"
fi

# -----------------------------------------------------------------------------
# 4) client/custom (JS, CSS)
# -----------------------------------------------------------------------------
echo ""
echo "==> 4) client/custom/"
if [[ -d "${CRM_ROOT}/client/custom" ]]; then
  tar -czf "${SNAP_DIR}/client-custom.tar.gz" -C "${CRM_ROOT}/client" custom
  echo "  OK: client-custom.tar.gz ($(du -h "${SNAP_DIR}/client-custom.tar.gz" | cut -f1))"
else
  echo "  (client/custom assente — skip)"
fi

# -----------------------------------------------------------------------------
# 5) data/ (config, upload — NO cache)
# -----------------------------------------------------------------------------
echo ""
echo "==> 5) data/ (config + upload, senza cache)"
DATA_ITEMS=()
[[ -f "${CRM_ROOT}/data/config.php" ]] && DATA_ITEMS+=(data/config.php)
[[ -f "${CRM_ROOT}/data/config-internal.php" ]] && DATA_ITEMS+=(data/config-internal.php)
[[ -d "${CRM_ROOT}/data/upload" ]] && DATA_ITEMS+=(data/upload)

if [[ ${#DATA_ITEMS[@]} -gt 0 ]]; then
  tar -czf "${SNAP_DIR}/data-config-upload.tar.gz" -C "${CRM_ROOT}" "${DATA_ITEMS[@]}"
  echo "  OK: data-config-upload.tar.gz ($(du -h "${SNAP_DIR}/data-config-upload.tar.gz" | cut -f1))"
  echo "  NOTA: config-internal.php contiene password DB — conservare in luogo sicuro."
else
  echo "  ATTENZIONE: nessun file data da archiviare"
fi

# -----------------------------------------------------------------------------
# 6) Elenco estensioni / moduli (riferimento)
# -----------------------------------------------------------------------------
echo ""
echo "==> 6) Elenco moduli installati"
if [[ -d "${CRM_ROOT}/data/upload/extensions" ]]; then
  ls -la "${CRM_ROOT}/data/upload/extensions" > "${SNAP_DIR}/extensions-list.txt" 2>/dev/null || true
fi
if [[ -f "${CRM_ROOT}/data/config.php" ]]; then
  php -r "
    \$c = include '${CRM_ROOT}/data/config.php';
    if (!empty(\$c['installedExtensions'])) {
      echo 'installedExtensions:' . PHP_EOL;
      print_r(\$c['installedExtensions']);
    }
  " > "${SNAP_DIR}/installed-extensions.txt" 2>/dev/null || true
fi
echo "  OK: extensions-list.txt / installed-extensions.txt (se presenti)"

# -----------------------------------------------------------------------------
# 7) Manifest
# -----------------------------------------------------------------------------
MANIFEST="${SNAP_DIR}/MANIFEST.txt"
{
  echo "backup_timestamp=${TS}"
  echo "crm_root=${CRM_ROOT}"
  echo "database=${DB_NAME}"
  echo "host=${DB_HOST}"
  echo "files:"
  ls -la "${SNAP_DIR}"
  echo ""
  echo "checksums_sha256:"
  (cd "${SNAP_DIR}" && sha256sum *.tar.gz *.sql.gz 2>/dev/null) || true
} > "${MANIFEST}"

echo ""
echo "=============================================="
echo " BACKUP COMPLETATO"
echo " Cartella: ${SNAP_DIR}"
echo ""
echo " Contenuto:"
echo "   - database-${DB_NAME}.sql.gz"
echo "   - custom.tar.gz"
echo "   - client-custom.tar.gz (se presente)"
echo "   - data-config-upload.tar.gz"
echo "   - MANIFEST.txt"
echo ""
echo " Prossimo passo: aggiornamento manuale EspoCRM 9.3.7"
echo "   Vedi: backup/pre-upgrade-9.3.7/README-AGGIORNAMENTO.md"
echo "=============================================="
