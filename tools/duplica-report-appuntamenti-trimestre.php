#!/usr/bin/env php
<?php
/**
 * Duplica i report "Appuntamenti Mese - …" in "Appuntamenti Ultimo Trimestre - …"
 * e allinea il layout del dashboard "Appuntamenti Ultimo Trimestre" a quello "Appuntamenti Mese".
 *
 *   php tools/duplica-report-appuntamenti-trimestre.php --dry-run
 *   php tools/duplica-report-appuntamenti-trimestre.php
 *   php tools/duplica-report-appuntamenti-trimestre.php --inspect
 */
declare(strict_types=1);

$root = getenv('CRM_ROOT') ?: getcwd();

if (!is_file($root . '/bootstrap.php')) {
    fwrite(STDERR, "Eseguire da root CRM (bootstrap.php).\n");
    exit(1);
}

require_once $root . '/bootstrap.php';

use Espo\Core\Application;
use Espo\ORM\Entity;

const SOURCE_PREFIX = 'Appuntamenti Mese - ';
const TARGET_PREFIX = 'Appuntamenti Ultimo Trimestre - ';
const DASHBOARD_SOURCE = 'Appuntamenti Mese';
const DASHBOARD_TARGET = 'Appuntamenti Ultimo Trimestre';

/** Valori filtro data tipici EspoCRM (mese → trimestre precedente). */
const DATE_VALUE_MAP = [
    'currentMonth' => 'previousQuarter',
    'thisMonth' => 'previousQuarter',
    'lastMonth' => 'previousQuarter',
];

$argv = $GLOBALS['argv'] ?? [];
$dryRun = in_array('--dry-run', $argv, true);
$inspect = in_array('--inspect', $argv, true);
$force = in_array('--force', $argv, true);

function backupDashboardLayouts($em, string $crmRoot): string
{
    $ts = date('Ymd-His');
    $dir = $crmRoot . '/backup_dev/Appuntamento/report-trimestre-' . $ts;
    mkdir($dir, 0755, true);

    $dashboardRepo = $em->getRDBRepository('Dashboard');

    foreach ([DASHBOARD_SOURCE, DASHBOARD_TARGET] as $name) {
        $dash = $dashboardRepo->where(['name' => $name])->findOne();

        if (!$dash) {
            continue;
        }

        $file = $dir . '/dashboard-' . preg_replace('/\s+/', '-', $name) . '.json';
        file_put_contents(
            $file,
            json_encode($dash->get('layout'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL
        );
    }

    return $dir;
}

$app = new Application();
$app->setupSystemUser();
$em = $app->getContainer()->get('entityManager');

function transformReportName(string $name): ?string
{
    if (!str_starts_with($name, SOURCE_PREFIX)) {
        return null;
    }

    $suffix = substr($name, strlen(SOURCE_PREFIX));
    $suffix = str_replace(' Mese', ' Trimestre', $suffix);

    return TARGET_PREFIX . $suffix;
}

/**
 * @param mixed $data
 * @return mixed
 */
function mapDateFilterValues($data)
{
    if (is_string($data)) {
        return DATE_VALUE_MAP[$data] ?? $data;
    }

    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = mapDateFilterValues($value);
        }

        return $data;
    }

    if (is_object($data)) {
        foreach (get_object_vars($data) as $key => $value) {
            $data->$key = mapDateFilterValues($value);
        }
    }

    return $data;
}

function copyReportFields(Entity $source, Entity $target): void
{
    $skip = [
        'id',
        'createdAt',
        'modifiedAt',
        'createdById',
        'modifiedById',
        'deleted',
    ];

    foreach ($source->getAttributeList() as $attribute) {
        if (in_array($attribute, $skip, true)) {
            continue;
        }

        if (!$source->has($attribute)) {
            continue;
        }

        $value = $source->get($attribute);

        if (in_array($attribute, ['filtersData', 'filtersDataList', 'filters', 'data', 'columnsData', 'chartDataList'], true)) {
            $value = mapDateFilterValues($value);
        }

        $target->set($attribute, $value);
    }
}

function transformDashletOptions(object $options, array $reportIdMap): object
{
    $options = clone $options;

    if (isset($options->reportId) && isset($reportIdMap[$options->reportId])) {
        $options->reportId = $reportIdMap[$options->reportId];
    }

    if (isset($options->title) && is_string($options->title)) {
        $options->title = str_replace(' Mese', ' Trimestre', $options->title);
        $options->title = str_replace('Mese', 'Trimestre', $options->title);
    }

    return $options;
}

/**
 * @param mixed $layout
 * @return mixed
 */
function transformDashboardLayout($layout, array $reportIdMap)
{
    if (!is_array($layout)) {
        return $layout;
    }

    $out = [];

    foreach ($layout as $cell) {
        if (!is_array($cell) && !is_object($cell)) {
            $out[] = $cell;
            continue;
        }

        $cell = json_decode(json_encode($cell), false);

        if (($cell->name ?? null) === 'Report' && isset($cell->options)) {
            $cell->options = transformDashletOptions($cell->options, $reportIdMap);
        }

        $out[] = $cell;
    }

    return $out;
}

$reportRepo = $em->getRDBRepository('Report');

if ($inspect) {
    echo "=== Report Mese (esempio) ===\n";
    foreach ($reportRepo->where(['entityType' => 'Appuntamento'])->find() as $report) {
        $name = (string) $report->get('name');
        if (!str_starts_with($name, SOURCE_PREFIX)) {
            continue;
        }
        echo $name . "\n";
        echo json_encode($report->get('filtersDataList'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
        break;
    }

    echo "=== Report Trimestre esistente (esempio) ===\n";
    foreach ($reportRepo->where(['entityType' => 'Appuntamento'])->find() as $report) {
        $name = (string) $report->get('name');
        if (!str_contains($name, 'Trimestre')) {
            continue;
        }
        echo $name . "\n";
        echo json_encode($report->get('filtersDataList'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
        break;
    }

    exit(0);
}

$reportIdMap = [];
$created = 0;
$updated = 0;
$skipped = 0;

foreach ($reportRepo->where(['entityType' => 'Appuntamento'])->find() as $source) {
    $sourceName = (string) $source->get('name');
    $targetName = transformReportName($sourceName);

    if ($targetName === null) {
        continue;
    }

    $existing = $reportRepo->where(['name' => $targetName])->findOne();

    if ($existing && !$force) {
        $reportIdMap[$source->getId()] = $existing->getId();
        $skipped++;
        echo "ESISTE: {$targetName}\n";
        continue;
    }

    if ($dryRun) {
        echo ($existing ? 'AGGIORNA' : 'CREA') . ": {$sourceName}\n  -> {$targetName}\n";
        continue;
    }

    if ($existing) {
        $target = $existing;
        copyReportFields($source, $target);
        $target->set('name', $targetName);
        $em->saveEntity($target);
        $reportIdMap[$source->getId()] = $target->getId();
        $updated++;
        echo "AGGIORNATO: {$targetName}\n";
        continue;
    }

    $target = $em->newEntity('Report');
    copyReportFields($source, $target);
    $target->set('name', $targetName);
    $em->saveEntity($target);
    $reportIdMap[$source->getId()] = $target->getId();
    $created++;
    echo "CREATO: {$targetName}\n";
}

if ($dryRun) {
    echo "\nDRY-RUN: nessuna modifica al database.\n";
    echo "Report da duplicare: vedi elenco sopra.\n";
    exit(0);
}

if ($reportIdMap === []) {
    fwrite(STDERR, "Nessun report mappato. Verificare nomi con prefisso \"" . SOURCE_PREFIX . "\".\n");
    exit(1);
}

$backupDir = backupDashboardLayouts($em, $root);
echo "Backup layout dashboard: {$backupDir}\n";

$dashboardRepo = $em->getRDBRepository('Dashboard');
$sourceDash = $dashboardRepo->where(['name' => DASHBOARD_SOURCE])->findOne();
$targetDash = $dashboardRepo->where(['name' => DASHBOARD_TARGET])->findOne();

if (!$sourceDash) {
    fwrite(STDERR, "Dashboard non trovata: " . DASHBOARD_SOURCE . "\n");
    exit(1);
}

if (!$targetDash) {
    fwrite(STDERR, "Dashboard non trovata: " . DASHBOARD_TARGET . "\n");
    exit(1);
}

$sourceLayout = $sourceDash->get('layout');
$newLayout = transformDashboardLayout($sourceLayout, $reportIdMap);
$targetDash->set('layout', $newLayout);
$em->saveEntity($targetDash);

echo "\nDashboard \"" . DASHBOARD_TARGET . "\" aggiornata (" . count(is_array($newLayout) ? $newLayout : []) . " dashlet).\n";
echo "Report creati: {$created}, aggiornati: {$updated}, già presenti: {$skipped}\n";
echo "Eseguire: php clear_cache.php && php rebuild.php\n";
echo "Poi Ctrl+F5 sul tab Appuntamenti Ultimo Trimestre.\n";
