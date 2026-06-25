#!/usr/bin/env php
<?php
/**
 * Ripristino dashboard: cerca backup ovunque, elenca utenti, rimuove tab KPI aggiunti.
 *
 *   php tools/rollback-dashboard-pre-kpi.php --list-users
 *   php tools/rollback-dashboard-pre-kpi.php --scan-all-backups
 *   php tools/rollback-dashboard-pre-kpi.php --user=carmine_alvino --restore-latest
 *   php tools/rollback-dashboard-pre-kpi.php --user=carmine_alvino --restore-dir=backup_dev/Appuntamento/report-trimestre-XXXX
 *   php tools/rollback-dashboard-pre-kpi.php --user=carmine_alvino --strip-kpi-tabs
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
use Espo\Core\ApplicationUser;
use Espo\Core\Container;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

$argv = $GLOBALS['argv'] ?? [];
$dryRun = in_array('--dry-run', $argv, true);
$listBackups = in_array('--list-backups', $argv, true);
$scanAll = in_array('--scan-all-backups', $argv, true);
$listUsers = in_array('--list-users', $argv, true);
$restoreLatest = in_array('--restore-latest', $argv, true);
$stripKpiTabs = in_array('--strip-kpi-tabs', $argv, true);
$restoreFrom = null;
$restoreDir = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--restore-from=')) {
        $restoreFrom = substr($arg, 15);
    }
    if (str_starts_with($arg, '--restore-dir=')) {
        $restoreDir = substr($arg, 14);
    }
}

/**
 * @return string[]
 */
function findAllDashboardBackupFiles(string $root): array
{
    $patterns = [
        'backup_dev/dashboard-crm-kpi-*/preferences-before.json',
        'backup_dev/dashboard-vendite-mese-*/preferences-before.json',
        'backup_dev/dashboard-restore-*/preferences-before-restore.json',
        'backup_dev/Appuntamento/**/preferences-*-before.json',
        'backup_dev/Appuntamento/**/dashboard-layout.json',
        'backup_dev/**/preferences-before.json',
        'backup_dev/**/dashboard-layout.json',
    ];

    $files = [];

    foreach ($patterns as $pattern) {
        foreach (glob($root . '/' . $pattern, GLOB_BRACE) ?: [] as $file) {
            if (is_file($file)) {
                $files[$file] = true;
            }
        }
    }

    // glob ricorsivo manuale per Appuntamento (GLOB_BRACE non sempre disponibile)
    $walk = static function (string $dir) use (&$walk, &$files): void {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $dir . '/' . $entry;

            if (is_dir($path)) {
                $walk($path);
                continue;
            }

            if (!is_file($path)) {
                continue;
            }

            $base = basename($path);

            if ($base === 'preferences-before.json'
                || $base === 'dashboard-layout.json'
                || str_contains($base, 'preferences-') && str_contains($base, '-before.json')
            ) {
                $files[$path] = true;
            }
        }
    };

    $walk($root . '/backup_dev/Appuntamento');
    $walk($root . '/backup_dev');

    $result = array_keys($files);
    usort($result, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

    return $result;
}

function printBackupEntry(string $file): void
{
    $tabs = loadTabsFromBackupFile($file);
    $mtime = @filemtime($file) ? date('Y-m-d H:i:s', filemtime($file)) : '?';
    echo "  {$file}\n";
    echo '    data: ' . $mtime . "\n";

    if ($tabs !== null && $tabs !== []) {
        echo '    tab: ' . implode(' | ', listTabNames($tabs)) . "\n";
    } else {
        echo "    (formato non riconosciuto come dashboard)\n";
    }

    echo "\n";
}

function listBackups(string $root): void
{
    $files = findAllDashboardBackupFiles($root);

    if ($files === []) {
        echo "Nessun backup dashboard trovato sotto backup_dev/\n\n";
        echo "Prova:\n";
        echo "  php tools/rollback-dashboard-pre-kpi.php --scan-all-backups\n";
        echo "  find backup_dev -type f \\( -name 'preferences*.json' -o -name 'dashboard-layout.json' \\)\n";
        echo "  php tools/rollback-dashboard-pre-kpi.php --user=USERNAME --strip-kpi-tabs\n";

        return;
    }

    echo "Backup dashboard trovati (più recente prima):\n\n";

    foreach ($files as $file) {
        printBackupEntry($file);
    }
}

function listCrmUsers(EntityManager $em): void
{
    echo "Utenti CRM attivi (usa --user=NOMEUTENTE):\n\n";

    foreach ($em->getRDBRepository('User')->where(['isActive' => true])->order('userName')->find() as $user) {
        echo '  ' . $user->get('userName') . ' [' . $user->get('type') . "]\n";
    }

    echo "\nEsempio: php tools/rollback-dashboard-pre-kpi.php --user=carmine_alvino --restore-latest\n";
}

function resolveUserStrict(Container $container, EntityManager $em, array $argv): Entity
{
    $requested = parseRunUserName($argv);
    $user = $em->getRDBRepository('User')->where(['userName' => $requested])->findOne();

    if (!$user) {
        echo "ERRORE: utente \"{$requested}\" non trovato.\n\n";
        listCrmUsers($em);
        fail('Usare --user=NOMEUTENTE dalla lista sopra.');
    }

    $container->getByClass(ApplicationUser::class)->setUser($user);
    echo 'Utente: ' . $user->get('userName') . "\n\n";

    return $user;
}

function restorePreferencesFromBackupDir(Entity $pref, EntityManager $em, string $dir): bool
{
    $dir = rtrim($dir, '/');

    foreach ([
        $dir . '/preferences-before.json',
        $dir . '/dashboard-layout.json',
    ] as $file) {
        $tabs = loadTabsFromBackupFile($file);

        if ($tabs !== null && $tabs !== []) {
            savePreferenceDashboardTabs($pref, $em, $tabs);

            return true;
        }
    }

    foreach (glob($dir . '/preferences-*-before.json') ?: [] as $file) {
        $tabs = loadTabsFromBackupFile($file);

        if ($tabs !== null && $tabs !== []) {
            savePreferenceDashboardTabs($pref, $em, $tabs);

            return true;
        }
    }

    $rawFile = $dir . '/preferences-data-raw.json';

    if (is_file($rawFile)) {
        $blob = json_decode((string) file_get_contents($rawFile), true);

        if (is_array($blob) && $blob !== []) {
            $pref->set('data', $blob);

            if (isset($blob['dashboardLayout'])) {
                $pref->set('dashboardLayout', $blob['dashboardLayout']);
            }

            return true;
        }
    }

    return false;
}

/**
 * @param array<int, array<string, mixed>> $tabs
 * @return array{0: array, 1: string[]}
 */
function stripKpiTabsFromDashboard(array $tabs): array
{
    $removed = [];
    $kept = [];

    foreach ($tabs as $tab) {
        if (!is_array($tab)) {
            continue;
        }

        $name = (string) ($tab['name'] ?? '');

        if ($name === 'Vendite Mese') {
            $removed[] = $name;
            continue;
        }

        if ($name === 'CRM') {
            $layout = is_array($tab['layout'] ?? null) ? $tab['layout'] : [];
            $nonKpi = array_values(array_filter($layout, static fn ($item) => is_array($item) && ($item['name'] ?? '') !== 'CrmKpi'));

            if ($nonKpi === []) {
                $removed[] = $name . ' (solo KPI)';
                continue;
            }

            $tab['layout'] = $nonKpi;
            $removed[] = 'CrmKpi dal tab CRM';
        }

        $kept[] = $tab;
    }

    return [$kept, $removed];
}

$app = new Application();
$container = $app->getContainer();
$em = $container->get('entityManager');

if ($listUsers) {
    listCrmUsers($em);
    exit(0);
}

if ($listBackups || $scanAll) {
    listBackups($root);

    if ($scanAll) {
        echo "--- Ricerca file JSON sospetti ---\n\n";
        $cmd = "find " . escapeshellarg($root . '/backup_dev') . " -type f \\( -name 'preferences*.json' -o -name 'dashboard-layout.json' \\) 2>/dev/null | head -30";
        passthru($cmd);
    }

    exit(0);
}

if (!$restoreLatest && $restoreFrom === null && $restoreDir === null && !$stripKpiTabs) {
    echo "Opzioni:\n";
    echo "  --list-users\n";
    echo "  --scan-all-backups\n";
    echo "  --user=... --restore-latest\n";
    echo "  --user=... --restore-from=PATH\n";
    echo "  --user=... --restore-dir=DIR\n";
    echo "  --user=... --strip-kpi-tabs\n";
    exit(1);
}

$user = resolveUserStrict($container, $em, $argv);
$pref = $em->getEntityById('Preferences', $user->getId());

if (!$pref) {
    fail('Preferenze non trovate per ' . $user->get('userName'));
}

$current = getPreferenceDashboardTabs($pref, $em) ?? [];
echo 'Tab attuali: ' . implode(' | ', listTabNames($current)) . "\n";

if ($stripKpiTabs) {
    [$newTabs, $removed] = stripKpiTabsFromDashboard($current);

    if ($removed === []) {
        echo "Nessun tab KPI/Vendite Mese da rimuovere.\n";
        exit(0);
    }

    echo 'Rimuoverei: ' . implode(', ', $removed) . "\n";
    echo 'Tab dopo: ' . implode(' | ', listTabNames($newTabs)) . "\n";

    if ($dryRun) {
        exit(0);
    }

    $backupDir = $root . '/backup_dev/dashboard-restore-' . date('Ymd-His');
    mkdir($backupDir, 0755, true);
    backupJson($backupDir, 'preferences-before-restore.json', $current);
    savePreferenceDashboardTabs($pref, $em, $newTabs);
    $em->saveEntity($pref);
    echo "\nTab KPI rimossi. Backup: {$backupDir}\n";
    echo "php clear_cache.php && logout/login\n";
    exit(0);
}

if ($restoreDir !== null) {
    if (!str_starts_with($restoreDir, '/')) {
        $restoreDir = $root . '/' . ltrim($restoreDir, '/');
    }

    if (!is_dir($restoreDir)) {
        fail('Cartella non trovata: ' . $restoreDir);
    }

    if ($dryRun) {
        echo "DRY-RUN: ripristinerei da {$restoreDir}\n";
        exit(0);
    }

    if (!restorePreferencesFromBackupDir($pref, $em, $restoreDir)) {
        fail('Nessun dato ripristinabile in ' . $restoreDir);
    }

    $em->saveEntity($pref);
    $tabs = getPreferenceDashboardTabs($pref, $em);
    echo "Ripristinato da: {$restoreDir}\n";
    echo 'Tab ora: ' . implode(' | ', listTabNames($tabs ?? [])) . "\n";
    echo "php clear_cache.php\n";
    exit(0);
}

if ($restoreLatest) {
    $files = findAllDashboardBackupFiles($root);

    if ($files === []) {
        echo "Nessun backup trovato.\n\n";
        echo "Prova una di queste:\n";
        echo "  php tools/rollback-dashboard-pre-kpi.php --scan-all-backups\n";
        echo "  php tools/rollback-dashboard-pre-kpi.php --user={$user->get('userName')} --strip-kpi-tabs\n";
        echo "  ls backup_dev/Appuntamento/\n";
        exit(1);
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
