#!/usr/bin/env bash
# Backup tab dashboard utente (preferences) prima di script UI/dashboard.
#
#   cd ~/public_html/crm/mec-group
#   bash tools/backup-dashboard-utente.sh carmine_alvino
#
# Output: backup_dev/Appuntamento/dashboard-backup-USER-STAMP/

set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$(pwd)}"
USER_NAME="${1:-${ESPO_USER:-carmine_alvino}}"
STAMP=$(date +%Y%m%d-%H%M%S)
OUT_DIR="${CRM_ROOT}/backup_dev/Appuntamento/dashboard-backup-${USER_NAME}-${STAMP}"

if [[ ! -f "${CRM_ROOT}/bootstrap.php" ]]; then
  echo "ERRORE: eseguire da root CRM (bootstrap.php)." >&2
  exit 1
fi

mkdir -p "${OUT_DIR}"

php -r "
require '${CRM_ROOT}/bootstrap.php';
\$em = (new Espo\Core\Application())->getContainer()->get('entityManager');
\$u = \$em->getRDBRepository('User')->where(['userName' => '${USER_NAME}'])->findOne();
if (!\$u) { fwrite(STDERR, 'Utente non trovato: ${USER_NAME}' . PHP_EOL); exit(1); }
\$pref = \$em->getEntityById('Preferences', \$u->getId());
if (!\$pref) { fwrite(STDERR, 'Preferenze non trovate' . PHP_EOL); exit(1); }
\$data = \$pref->get('data');
\$tabs = \$pref->get('dashboardLayout');
if (\$tabs === null && is_object(\$data) && isset(\$data->dashboardLayout)) {
    \$tabs = \$data->dashboardLayout;
}
file_put_contents('${OUT_DIR}/preferences-data-raw.json', json_encode(\$data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
file_put_contents('${OUT_DIR}/dashboard-layout.json', json_encode(\$tabs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
file_put_contents('${OUT_DIR}/meta.json', json_encode([
    'userName' => '${USER_NAME}',
    'userId' => \$u->getId(),
    'createdAt' => '${STAMP}',
    'tabNames' => array_map(fn(\$t) => is_array(\$t) ? (\$t['name'] ?? '') : '', is_array(\$tabs) ? \$tabs : []),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo 'OK backup dashboard ${USER_NAME} -> ${OUT_DIR}', PHP_EOL;
"

echo ""
echo "Rollback:"
echo "  php tools/rollback-dashboard-pre-kpi.php --user=${USER_NAME} --restore-dir=${OUT_DIR}"
