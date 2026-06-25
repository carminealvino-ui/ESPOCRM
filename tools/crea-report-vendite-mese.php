#!/usr/bin/env php
<?php
/**
 * Crea report "Vendite Mese" in elenco CRM (Advanced Pack).
 * La dashboard NON viene modificata salvo --dashboard-only esplicito.
 *
 *   php tools/crea-report-vendite-mese.php --reports-only --force
 *   php tools/crea-report-vendite-mese.php --dashboard-only --user=carmine_alvino
 *
 * --force rigenera solo i report, mai i tab dashboard esistenti.
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
use Espo\Core\Record\CreateParams;
use Espo\Core\Record\DeleteParams;
use Espo\Core\Record\ServiceContainer as RecordServiceContainer;

$argv = $GLOBALS['argv'] ?? [];
$dryRun = in_array('--dry-run', $argv, true);
$force = in_array('--force', $argv, true);
$reportsOnly = in_array('--reports-only', $argv, true);
$dashboardOnly = in_array('--dashboard-only', $argv, true);

if (!$reportsOnly && !$dashboardOnly) {
    $reportsOnly = true;
    echo "Nota: per default creo solo i report (--reports-only). Per il tab dashboard usare --dashboard-only.\n\n";
}

if ($reportsOnly && $dashboardOnly) {
    fail('Usare solo uno tra --reports-only e --dashboard-only.');
}

$templatePath = __DIR__ . '/report-templates/vendite-mese.json';

if (!is_file($templatePath)) {
    fail('Template non trovato: ' . $templatePath);
}

/** @var array<string, mixed> $template */
$template = json_decode((string) file_get_contents($templatePath), true, 512, JSON_THROW_ON_ERROR);

$app = new Application();
$container = $app->getContainer();
$em = $container->get('entityManager');
/** @var RecordServiceContainer $recordServiceContainer */
$recordServiceContainer = $container->get('recordServiceContainer');
$reportService = $recordServiceContainer->get('Report');

$user = setupRunUser($container, $em, $argv);

$tabName = (string) ($template['tabName'] ?? 'Vendite Mese');
$prefix = (string) ($template['prefix'] ?? 'Vendite Mese - ');
$definitions = $template['reports'] ?? [];

echo "=== Crea report Vendite Mese ===\n\n";

$reportIdMap = [];
$created = 0;
$skipped = 0;

if (!$dashboardOnly) {
    foreach ($definitions as $definition) {
        $name = (string) ($definition['name'] ?? '');

        if ($name === '') {
            continue;
        }

        $existing = $em->getRDBRepository('Report')->where(['name' => $name])->findOne();

        if ($existing && !$force) {
            $reportIdMap[$name] = $existing->getId();
            $skipped++;
            echo "ESISTE: {$name}\n";
            continue;
        }

        if ($dryRun) {
            echo ($existing ? 'RIGENERA' : 'CREA') . ": {$name}\n";
            continue;
        }

        if ($existing && $force) {
            try {
                $reportService->delete($existing->getId(), DeleteParams::create());
            } catch (Throwable $e) {
                $em->removeEntity($existing);
            }

            echo "ELIMINATO: {$name}\n";
        }

        try {
            $attributes = buildReportAttributes($definition);
            $createResult = $reportService->create($attributes, CreateParams::create()->withSkipDuplicateCheck(true));
            $entity = $createResult instanceof \Espo\ORM\Entity ? $createResult : $createResult->getEntity();
            $reportIdMap[$name] = $entity->getId();
            $created++;
            echo "CREATO: {$name} (id={$entity->getId()})\n";
        } catch (Throwable $e) {
            echo "ERRORE {$name}: {$e->getMessage()}\n";
        }
    }
}

if ($dryRun) {
    echo "\nDRY-RUN completato.\n";
    exit(0);
}

if ($reportsOnly) {
    echo "\nReport creati: {$created}, già presenti: {$skipped}\n";
    echo "I tab dashboard esistenti non sono stati modificati.\n";
    exit(0);
}

if ($reportIdMap === []) {
    foreach ($definitions as $definition) {
        $name = (string) ($definition['name'] ?? '');
        $existing = $em->getRDBRepository('Report')->where(['name' => $name])->findOne();

        if ($existing) {
            $reportIdMap[$name] = $existing->getId();
        }
    }
}

if ($reportIdMap === []) {
    fail('Nessun report Vendite Mese trovato. Eseguire prima --reports-only --force');
}

$layout = buildReportTabLayout($reportIdMap, $definitions, $prefix);

$pref = $em->getEntityById('Preferences', $user->getId());

if (!$pref) {
    fail('Preferenze non trovate per ' . $user->get('userName'));
}

$tabs = getPreferenceDashboardTabs($pref, $em) ?? [];

if (in_array($tabName, listTabNames($tabs), true)) {
    echo "Tab \"{$tabName}\" già presente: nessuna modifica (tab esistenti protetti).\n";
    echo "Aggiungere i report manualmente da CRM oppure ripristinare backup se serve.\n";
    exit(0);
}

$backupDir = $root . '/backup_dev/dashboard-vendite-mese-' . date('Ymd-His');
mkdir($backupDir, 0755, true);
backupJson($backupDir, 'preferences-before.json', $tabs);

[$newTabs, $changed] = appendDashboardTabIfMissing($tabs, $tabName, $layout);

if (!$changed) {
    echo "Nessuna modifica.\n";
    exit(0);
}

savePreferenceDashboardTabs($pref, $em, $newTabs);
$em->saveEntity($pref);

echo "Creato tab \"{$tabName}\" con " . countReportDashlets($layout) . " report (tab preesistenti intatti).\n";
echo "Backup: {$backupDir}\n";
echo "Rollback: php tools/rollback-dashboard-pre-kpi.php --restore-from={$backupDir}/preferences-before.json\n";
