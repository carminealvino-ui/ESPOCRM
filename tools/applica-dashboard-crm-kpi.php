#!/usr/bin/env php
<?php
/**
 * Aggiunge il dashlet KPI al tab "CRM" SENZA toccare gli altri tab o dashlet.
 *
 *   php tools/applica-dashboard-crm-kpi.php --dry-run
 *   php tools/applica-dashboard-crm-kpi.php --user=carmine_alvino
 *
 * NON usa --force: non sostituisce mai layout esistenti.
 * Per annullare: php tools/rollback-dashboard-pre-kpi.php --restore-latest
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

const TAB_NAME = 'CRM';
const DASHLET_NAME = 'CrmKpi';

$argv = $GLOBALS['argv'] ?? [];
$dryRun = in_array('--dry-run', $argv, true);

if (in_array('--force', $argv, true)) {
    fail('Opzione --force rimossa: non sostituiamo più i tab esistenti. Usare rollback-dashboard-pre-kpi.php per ripristinare.');
}

$app = new Application();
$container = $app->getContainer();
$em = $container->get('entityManager');

$user = setupRunUser($container, $em, $argv);

$dashlet = [
    'id' => 'crm-kpi-main',
    'name' => DASHLET_NAME,
    'x' => 0,
    'y' => 0,
    'width' => 4,
    'height' => 6,
    'options' => [
        'title' => 'KPI CRM',
        'period' => 'currentMonth',
        'autorefreshInterval' => 5,
    ],
];

$pref = $em->getEntityById('Preferences', $user->getId());

if (!$pref) {
    fail('Preferenze non trovate.');
}

$tabs = getPreferenceDashboardTabs($pref, $em) ?? [];

if ($dryRun) {
    $hasTab = in_array(TAB_NAME, listTabNames($tabs), true);
    echo 'DRY-RUN: ' . ($hasTab ? 'unisco' : 'creo') . ' dashlet KPI nel tab "' . TAB_NAME . "\"\n";
    echo 'Tab attuali: ' . implode(' | ', listTabNames($tabs)) . "\n";
    exit(0);
}

$backupDir = $root . '/backup_dev/dashboard-crm-kpi-' . date('Ymd-His');
mkdir($backupDir, 0755, true);
backupJson($backupDir, 'preferences-before.json', $tabs);

[$newTabs, $changed, $mode] = appendDashletToDashboardTab($tabs, TAB_NAME, $dashlet, DASHLET_NAME);

if (!$changed) {
    echo "Nessuna modifica necessaria.\n";
    exit(0);
}

savePreferenceDashboardTabs($pref, $em, $newTabs);
$em->saveEntity($pref);

echo ($mode === 'created' ? 'Creato' : 'Aggiornato') . ' tab "' . TAB_NAME . '" con dashlet KPI (altri dashlet intatti).' . "\n";
echo "Backup: {$backupDir}\n";
echo "Eseguire: php clear_cache.php\n";
echo "Rollback: php tools/rollback-dashboard-pre-kpi.php --restore-from={$backupDir}/preferences-before.json\n";
