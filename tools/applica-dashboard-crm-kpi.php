#!/usr/bin/env php
<?php
/**
 * Aggiunge/aggiorna il tab dashboard "CRM" con il dashlet KPI.
 *
 *   php tools/applica-dashboard-crm-kpi.php --dry-run
 *   php tools/applica-dashboard-crm-kpi.php --force --user=admin
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

$argv = $GLOBALS['argv'] ?? [];
$dryRun = in_array('--dry-run', $argv, true);
$force = in_array('--force', $argv, true);

$app = new Application();
$container = $app->getContainer();
$em = $container->get('entityManager');

setupRunUser($container, $em, $argv);

$layout = [
    [
        'id' => 'crm-kpi-main',
        'name' => 'CrmKpi',
        'x' => 0,
        'y' => 0,
        'width' => 4,
        'height' => 6,
        'options' => [
            'title' => 'KPI CRM',
            'period' => 'currentMonth',
            'autorefreshInterval' => 5,
        ],
    ],
];

$userName = parseRunUserName($argv);
$user = $em->getRDBRepository('User')->where(['userName' => $userName])->findOne();

if (!$user) {
    fail('Utente non trovato: ' . $userName);
}

$pref = $em->getEntityById('Preferences', $user->getId());

if (!$pref) {
    fail('Preferenze non trovate.');
}

$tabs = getPreferenceDashboardTabs($pref, $em) ?? [];

if ($dryRun) {
    echo "DRY-RUN: tab \"" . TAB_NAME . "\" con dashlet CrmKpi (4×6)\n";
    exit(0);
}

$backupDir = $root . '/backup_dev/dashboard-crm-kpi-' . date('Ymd-His');
mkdir($backupDir, 0755, true);
backupJson($backupDir, 'preferences-before.json', $tabs);

$replace = $force;
$found = false;

foreach ($tabs as $i => $tab) {
    if (!is_array($tab)) {
        continue;
    }

    if (($tab['name'] ?? '') === TAB_NAME) {
        $found = true;

        if ($replace) {
            $tabs[$i]['layout'] = $layout;
            echo "Aggiornato tab \"" . TAB_NAME . "\".\n";
        } else {
            echo "Tab \"" . TAB_NAME . "\" già presente (usare --force per sostituire il layout).\n";
            exit(0);
        }
    }
}

if (!$found) {
    $tabs[] = [
        'name' => TAB_NAME,
        'layout' => $layout,
    ];
    echo "Creato tab \"" . TAB_NAME . "\".\n";
}

savePreferenceDashboardTabs($pref, $em, $tabs);
$em->saveEntity($pref);

echo "Backup: {$backupDir}\n";
echo "Eseguire: php clear_cache.php && php rebuild.php\n";
echo "Poi Ctrl+F5 sul tab CRM.\n";
