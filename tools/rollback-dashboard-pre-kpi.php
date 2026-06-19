#!/usr/bin/env php
<?php
/**
 * Ripristina la dashboard dalle cartelle backup create dagli script KPI/Vendite.
 * NON modifica tab esistenti se usato in modalità restore.
 *
 *   php tools/rollback-dashboard-pre-kpi.php --list-backups
 *   php tools/rollback-dashboard-pre-kpi.php --user=carmine_alvino --restore-latest
 *   php tools/rollback-dashboard-pre-kpi.php --user=carmine_alvino --restore-from=backup_dev/dashboard-crm-kpi-20260619-213500/preferences-before.json
 */
declare(strict_types=1);

$root = getenv('CRM_ROOT') ?: getcwd();

if (!is_file($root . '/bootstrap.php')) {
    fwrite(STDERR, "Eseguire da root CRM (bootstrap.php).\n");
    exit(1);
}

require_once $root . '/bootstrap.php';
require_once __DIR__ . '/lib/dashboard-report-helpers.php';

use Espo\Core\Application;

const BACKUP_GLOBS = [
    'backup_dev/dashboard-crm-kpi-*',
    'backup_dev/dashboard-vendite-mese-*',
];

$argv = $GLOBALS['argv'] ?? [];
$dryRun = in_array('--dry-run', $argv, true);
$listBackups = in_array('--list-backups', $argv, true);
$restoreLatest = in_array('--restore-latest', $argv, true);
$restoreFrom = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--restore-from=')) {
        $restoreFrom = substr($arg, 15);
    }
}

function findBackupFiles(string $root): array
{
    $files = [];

    foreach (BACKUP_GLOBS as $pattern) {
        foreach (glob($root . '/' . $pattern . '/preferences-before.json') ?: [] as $file) {
            $files[] = $file;
        }
    }

    usort($files, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

    return $files;
}

function listBackups(string $root): void
{
    $files = findBackupFiles($root);

    if ($files === []) {
        echo "Nessun backup trovato in backup_dev/dashboard-crm-kpi-* o dashboard-vendite-mese-*\n";
        echo "Cerca anche backup manuali:\n";
        echo "  ls -lt backup_dev/dashboard-*/preferences-before.json\n";

        return;
    }

    echo "Backup dashboard (più recente prima):\n\n";

    foreach ($files as $file) {
        $tabs = loadTabsFromBackupFile($file);
        $mtime = date('Y-m-d H:i:s', filemtime($file));
        echo "  {$file}\n";
        echo '    data: ' . $mtime . "\n";
        echo '    tab: ' . implode(' | ', listTabNames($tabs ?? [])) . "\n\n";
    }
}

$app = new Application();
$container = $app->getContainer();
$em = $container->get('entityManager');

if ($listBackups) {
    listBackups($root);
    exit(0);
}

if (!$restoreLatest && $restoreFrom === null) {
    fail('Specificare --list-backups, --restore-latest o --restore-from=PATH');
}

$user = setupRunUser($container, $em, $argv);
$pref = $em->getEntityById('Preferences', $user->getId());

if (!$pref) {
    fail('Preferenze non trovate per ' . $user->get('userName'));
}

if ($restoreLatest) {
    $files = findBackupFiles($root);

    if ($files === []) {
        fail('Nessun backup trovato.');
    }

    $restoreFrom = $files[0];
    echo "Uso backup più recente: {$restoreFrom}\n";
}

if ($restoreFrom !== null && !str_starts_with($restoreFrom, '/')) {
    $restoreFrom = $root . '/' . ltrim($restoreFrom, '/');
}

$tabs = loadTabsFromBackupFile((string) $restoreFrom);

if ($tabs === null || $tabs === []) {
    fail('Backup non valido o vuoto: ' . $restoreFrom);
}

$current = getPreferenceDashboardTabs($pref, $em) ?? [];
echo 'Tab attuali: ' . implode(' | ', listTabNames($current)) . "\n";
echo 'Tab nel backup: ' . implode(' | ', listTabNames($tabs)) . "\n";

if ($dryRun) {
    echo "\nDRY-RUN: ripristinerei da {$restoreFrom}\n";
    exit(0);
}

$backupDir = $root . '/backup_dev/dashboard-restore-' . date('Ymd-His');
mkdir($backupDir, 0755, true);
backupJson($backupDir, 'preferences-before-restore.json', $current);

savePreferenceDashboardTabs($pref, $em, $tabs);
$em->saveEntity($pref);

echo "\nRipristinato da: {$restoreFrom}\n";
echo "Backup stato precedente: {$backupDir}/preferences-before-restore.json\n";
echo "Eseguire: php clear_cache.php\n";
echo "Poi logout/login o Ctrl+F5.\n";
