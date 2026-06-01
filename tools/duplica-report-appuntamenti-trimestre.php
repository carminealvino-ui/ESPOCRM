#!/usr/bin/env php
<?php
/**
 * Duplica report "Appuntamenti Mese - …" → "Appuntamenti Ultimo Trimestre - …"
 * e copia il layout del tab dashboard da "Appuntamenti Mese" a "Appuntamenti Ultimo Trimestre"
 * (Preferenze utente + config di sistema).
 *
 *   php tools/duplica-report-appuntamenti-trimestre.php --dry-run
 *   php tools/duplica-report-appuntamenti-trimestre.php --reports-only --force   # passo 1: elenco Report
 *   php tools/duplica-report-appuntamenti-trimestre.php --dashboard-only --force # passo 2: tab dashboard
 *   php tools/duplica-report-appuntamenti-trimestre.php --force                  # entrambi
 *   php tools/duplica-report-appuntamenti-trimestre.php --diagnose
 */
declare(strict_types=1);

$root = getenv('CRM_ROOT') ?: getcwd();

if (!is_file($root . '/bootstrap.php')) {
    fwrite(STDERR, "Eseguire da root CRM (bootstrap.php).\n");
    exit(1);
}

require_once $root . '/bootstrap.php';

use Espo\Core\Application;
use Espo\Core\Utils\Config;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

const SOURCE_PREFIX = 'Appuntamenti Mese - ';
const TARGET_PREFIX = 'Appuntamenti Ultimo Trimestre - ';
const TAB_SOURCE = 'Appuntamenti Mese';
const TAB_TARGET = 'Appuntamenti Ultimo Trimestre';

const DATE_VALUE_MAP = [
    'currentMonth' => 'previousQuarter',
    'thisMonth' => 'previousQuarter',
    'lastMonth' => 'previousQuarter',
];

$argv = $GLOBALS['argv'] ?? [];
$dryRun = in_array('--dry-run', $argv, true);
$diagnose = in_array('--diagnose', $argv, true);
$force = in_array('--force', $argv, true);
$reportsOnly = in_array('--reports-only', $argv, true);
$dashboardOnly = in_array('--dashboard-only', $argv, true);
$verbose = in_array('--verbose', $argv, true);

if ($reportsOnly && $dashboardOnly) {
    fwrite(STDERR, "Usare solo uno tra --reports-only e --dashboard-only.\n");
    exit(1);
}

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();
/** @var EntityManager $em */
$em = $container->get('entityManager');
/** @var Config $config */
$config = $container->get('config');

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

function resolveAppuntamentiCategoryId(EntityManager $em, Entity $source): ?string
{
    if ($source->get('categoryId')) {
        return $source->get('categoryId');
    }

    $catRepo = $em->getRDBRepository('ReportCategory');

    foreach (['Appuntamenti', 'appuntamenti'] as $label) {
        $cat = $catRepo->where(['name' => $label])->findOne();

        if ($cat) {
            return $cat->getId();
        }
    }

    foreach ($catRepo->find() as $cat) {
        if (stripos((string) $cat->get('name'), 'appuntament') !== false) {
            return $cat->getId();
        }
    }

    return null;
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
        'isInternal',
        'internalClassName',
        'internalParams',
    ];

    foreach ($source->getAttributeList() as $attribute) {
        if (in_array($attribute, $skip, true) || !$source->has($attribute)) {
            continue;
        }

        $value = $source->get($attribute);

        if (in_array($attribute, ['filtersData', 'filtersDataList', 'filters', 'data', 'columnsData', 'chartDataList'], true)) {
            $value = mapDateFilterValues($value);
        }

        $target->set($attribute, $value);
    }
}

function buildReportIdMap(EntityManager $em): array
{
    $map = [];
    $reportRepo = $em->getRDBRepository('Report');

    foreach ($reportRepo->where(['entityType' => 'Appuntamento'])->find() as $source) {
        $targetName = transformReportName((string) $source->get('name'));

        if ($targetName === null) {
            continue;
        }

        $target = $reportRepo->where(['name' => $targetName])->findOne();

        if ($target) {
            $map[$source->getId()] = $target->getId();
        }
    }

    return $map;
}

/**
 * @param mixed $layout
 */
function countReportDashlets($layout): int
{
    if (!is_array($layout)) {
        return 0;
    }

    $count = 0;

    foreach ($layout as $item) {
        if (!is_array($item)) {
            continue;
        }

        if (($item['name'] ?? null) === 'Report') {
            $count++;
        }

        foreach ($item as $value) {
            if (is_array($value)) {
                $count += countReportDashlets($value);
            }
        }
    }

    return $count;
}

/**
 * @param mixed $layout
 * @return mixed
 */
function remapLayoutReportIds($layout, array $reportIdMap, EntityManager $em)
{
    if (!is_array($layout)) {
        return $layout;
    }

    $reportRepo = $em->getRDBRepository('Report');

    $walk = function (array &$node) use (&$walk, $reportIdMap, $reportRepo): void {
        if (($node['name'] ?? null) === 'Report' && isset($node['options']) && is_array($node['options'])) {
            $opts = &$node['options'];

            if (!empty($opts['reportId'])) {
                $oldId = $opts['reportId'];

                if (isset($reportIdMap[$oldId])) {
                    $opts['reportId'] = $reportIdMap[$oldId];
                } else {
                    $report = $reportRepo->getById($oldId);

                    if ($report) {
                        $targetName = transformReportName((string) $report->get('name'));

                        if ($targetName) {
                            $target = $reportRepo->where(['name' => $targetName])->findOne();

                            if ($target) {
                                $opts['reportId'] = $target->getId();
                            }
                        }
                    }
                }
            }

            if (!empty($opts['title']) && is_string($opts['title'])) {
                $opts['title'] = str_replace(' Mese', ' Trimestre', $opts['title']);
            }
        }

        foreach ($node as &$value) {
            if (is_array($value)) {
                $walk($value);
            }
        }
    };

    $copy = json_decode(json_encode($layout), true);
    $walk($copy);

    return $copy;
}

/**
 * @param mixed $tabs
 * @return array{0: mixed, 1: bool, 2: int}
 */
function syncTrimestreTab($tabs, array $reportIdMap, EntityManager $em): array
{
    if (!is_array($tabs)) {
        return [$tabs, false, 0];
    }

    $sourceLayout = null;

    foreach ($tabs as $tab) {
        if (!is_array($tab)) {
            continue;
        }

        if (($tab['name'] ?? '') === TAB_SOURCE) {
            $sourceLayout = $tab['layout'] ?? null;
            break;
        }
    }

    if ($sourceLayout === null) {
        return [$tabs, false, 0];
    }

    $newLayout = remapLayoutReportIds($sourceLayout, $reportIdMap, $em);
    $dashletCount = countReportDashlets($newLayout);
    $changed = false;
    $targetFound = false;

    foreach ($tabs as $i => $tab) {
        if (!is_array($tab)) {
            continue;
        }

        if (($tab['name'] ?? '') === TAB_TARGET) {
            $tabs[$i]['layout'] = $newLayout;
            $targetFound = true;
            $changed = true;
            break;
        }
    }

    if (!$targetFound) {
        $tabs[] = [
            'name' => TAB_TARGET,
            'layout' => $newLayout,
        ];
        $changed = true;
    }

    return [$tabs, $changed, $dashletCount];
}

function listTabNames($tabs): array
{
    if (!is_array($tabs)) {
        return [];
    }

    $names = [];

    foreach ($tabs as $tab) {
        if (is_array($tab) && isset($tab['name'])) {
            $names[] = (string) $tab['name'];
        }
    }

    return $names;
}

function backupJson(string $dir, string $file, $data): void
{
    file_put_contents(
        $dir . '/' . $file,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL
    );
}

if ($diagnose) {
    echo "=== Diagnostica dashboard ===\n\n";

    $prefRepo = $em->getRDBRepository('Preferences');
    $n = 0;

    foreach ($prefRepo->find() as $pref) {
        $tabs = $pref->get('dashboardLayout');

        if (!is_array($tabs) || $tabs === []) {
            continue;
        }

        $names = listTabNames($tabs);
        $hasMese = in_array(TAB_SOURCE, $names, true);
        $hasTrim = in_array(TAB_TARGET, $names, true);

        if (!$hasMese && !$hasTrim) {
            continue;
        }

        $n++;
        $userId = $pref->getId();
        $user = $em->getEntityById('User', $userId);
        $userName = $user ? $user->get('userName') : $userId;

        echo "User: {$userName}\n";
        echo '  Tab: ' . implode(' | ', $names) . "\n";

        foreach ($tabs as $tab) {
            if (!is_array($tab)) {
                continue;
            }

            $name = $tab['name'] ?? '';

            if ($name === TAB_SOURCE || $name === TAB_TARGET) {
                $cnt = countReportDashlets($tab['layout'] ?? []);
                echo "  {$name}: {$cnt} dashlet Report\n";
            }
        }

        echo "\n";
    }

    echo "Preferenze con tab Mese/Trimestre: {$n}\n";

    $cfgLayout = $config->get('dashboardLayout');
    if (is_array($cfgLayout)) {
        echo "\nConfig dashboardLayout tab: " . implode(' | ', listTabNames($cfgLayout)) . "\n";
    }

    $defaultLayouts = $config->get('defaultDashboardLayouts');
    if (is_array($defaultLayouts)) {
        echo "Config defaultDashboardLayouts chiavi: " . implode(', ', array_keys($defaultLayouts)) . "\n";
    }

    exit(0);
}

$reportRepo = $em->getRDBRepository('Report');
$reportIdMap = [];
$created = 0;
$updated = 0;
$skipped = 0;

if ($dashboardOnly) {
    echo "Passo 2: aggiornamento dashboard (report già in elenco).\n\n";
} else {
    echo "Passo 1: creazione report in elenco CRM.\n\n";
}

if (!$dashboardOnly) {
foreach ($reportRepo->where(['entityType' => 'Appuntamento'])->find() as $source) {
    $sourceName = (string) $source->get('name');
    $targetName = transformReportName($sourceName);

    if ($targetName === null) {
        continue;
    }

    $existing = $reportRepo->where(['name' => $targetName])->findOne();

    if ($existing && !$force && !$dryRun) {
        $reportIdMap[$source->getId()] = $existing->getId();
        $skipped++;
        echo "ESISTE: {$targetName}\n";
        continue;
    }

    if ($dryRun) {
        echo ($existing ? 'AGGIORNA' : 'CREA') . ": {$sourceName}\n  -> {$targetName}\n";
        continue;
    }

    $categoryId = resolveAppuntamentiCategoryId($em, $source);

    if ($existing) {
        $target = $existing;
        copyReportFields($source, $target);
        $target->set('name', $targetName);

        if ($categoryId) {
            $target->set('categoryId', $categoryId);
        }

        try {
            $em->saveEntity($target);
        } catch (Throwable $e) {
            echo "ERRORE AGGIORNA {$targetName}: {$e->getMessage()}\n";
            continue;
        }

        $reportIdMap[$source->getId()] = $target->getId();
        $updated++;
        echo "AGGIORNATO: {$targetName}" . ($categoryId ? '' : ' (senza categoria!)') . "\n";
        continue;
    }

    $target = $em->newEntity('Report');
    copyReportFields($source, $target);
    $target->set('name', $targetName);
    $target->set('entityType', 'Appuntamento');

    if ($categoryId) {
        $target->set('categoryId', $categoryId);
    }

    try {
        $em->saveEntity($target);
    } catch (Throwable $e) {
        echo "ERRORE CREA {$targetName}: {$e->getMessage()}\n";
        if ($verbose) {
            echo $e->getTraceAsString() . "\n";
        }
        continue;
    }

    $reportIdMap[$source->getId()] = $target->getId();
    $created++;
    echo "CREATO: {$targetName} (id={$target->getId()})" . ($categoryId ? '' : ' ATTENZIONE: categoria Appuntamenti non trovata') . "\n";
}
}

if ($dryRun) {
    echo "\nDRY-RUN: nessuna modifica.\n";
    echo "Passo 1: php tools/duplica-report-appuntamenti-trimestre.php --reports-only --force\n";
    echo "Passo 2: php tools/duplica-report-appuntamenti-trimestre.php --dashboard-only --force\n";
    exit(0);
}

echo "\n=== Report in CRM (Report > Appuntamenti) ===\n";
$listed = 0;

foreach ($reportRepo->where(['entityType' => 'Appuntamento'])->find() as $report) {
    $name = (string) $report->get('name');

    if (!str_starts_with($name, TARGET_PREFIX)) {
        continue;
    }

    echo "  - {$name}\n";
    $listed++;
}

echo "Totale \"" . TARGET_PREFIX . "*\": {$listed} (attesi 8)\n";

try {
    $pdo = $em->getPDO();
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM `report` WHERE deleted = 0 AND name LIKE :prefix'
    );
    $stmt->execute(['prefix' => TARGET_PREFIX . '%']);
    $sqlCount = (int) $stmt->fetchColumn();
    echo "Verifica SQL tabella report: {$sqlCount} righe con prefisso trimestre.\n";
} catch (Throwable $e) {
    echo "Verifica SQL non disponibile: {$e->getMessage()}\n";
}

if ($listed === 0) {
    echo "\nCerco report con 'Trimestre' nel nome (qualsiasi prefisso):\n";
    foreach ($reportRepo->find() as $report) {
        $name = (string) $report->get('name');
        if (stripos($name, 'trimestre') !== false && stripos($name, 'appuntament') !== false) {
            echo "  - {$name} (categoryId=" . ($report->get('categoryId') ?: 'NULL') . ")\n";
        }
    }
}

if ($reportsOnly) {
    echo "\nPasso 1 completato. Verificare l'elenco in CRM, poi eseguire --dashboard-only --force\n";
    exit($listed >= 8 ? 0 : 1);
}

if ($reportIdMap === []) {
    $reportIdMap = buildReportIdMap($em);
}

if ($reportIdMap === []) {
    fwrite(STDERR, "Nessun report mappato. Eseguire prima --reports-only --force\n");
    exit(1);
}

if ($dashboardOnly && $listed < 8) {
    fwrite(STDERR, "Solo {$listed}/8 report trimestre in elenco. Completare passo 1.\n");
    exit(1);
}

$backupDir = $root . '/backup_dev/Appuntamento/report-trimestre-' . date('Ymd-His');
mkdir($backupDir, 0755, true);

$prefUpdated = 0;
$prefRepo = $em->getRDBRepository('Preferences');

foreach ($prefRepo->find() as $pref) {
    $tabs = $pref->get('dashboardLayout');

    if (!is_array($tabs) || !in_array(TAB_SOURCE, listTabNames($tabs), true)) {
        continue;
    }

    backupJson($backupDir, 'preferences-' . $pref->getId() . '-before.json', $tabs);

    [$newTabs, $changed, $dashletCount] = syncTrimestreTab($tabs, $reportIdMap, $em);

    if (!$changed) {
        continue;
    }

    $pref->set('dashboardLayout', $newTabs);
    $em->saveEntity($pref);
    $prefUpdated++;

    $user = $em->getEntityById('User', $pref->getId());
    $userName = $user ? $user->get('userName') : $pref->getId();
    echo "PREFERENZE: {$userName} → tab \"" . TAB_TARGET . "\" con {$dashletCount} dashlet Report\n";
}

$configWriter = $container->get('configWriter');
$configChanged = false;

foreach (['dashboardLayout'] as $configKey) {
    $tabs = $config->get($configKey);

    if (!is_array($tabs) || !in_array(TAB_SOURCE, listTabNames($tabs), true)) {
        continue;
    }

    backupJson($backupDir, 'config-' . $configKey . '-before.json', $tabs);

    [$newTabs, $changed, $dashletCount] = syncTrimestreTab($tabs, $reportIdMap, $em);

    if ($changed) {
        $configWriter->set($configKey, $newTabs);
        $configChanged = true;
        echo "CONFIG {$configKey}: tab \"" . TAB_TARGET . "\" con {$dashletCount} dashlet Report\n";
    }
}

$defaultLayouts = $config->get('defaultDashboardLayouts');

if (is_array($defaultLayouts)) {
    foreach ($defaultLayouts as $layoutKey => $tabs) {
        if (!is_array($tabs) || !in_array(TAB_SOURCE, listTabNames($tabs), true)) {
            continue;
        }

        backupJson($backupDir, 'config-defaultDashboardLayouts-' . $layoutKey . '-before.json', $tabs);

        [$newTabs, $changed, $dashletCount] = syncTrimestreTab($tabs, $reportIdMap, $em);

        if ($changed) {
            $defaultLayouts[$layoutKey] = $newTabs;
            echo "CONFIG defaultDashboardLayouts[{$layoutKey}]: {$dashletCount} dashlet\n";
            $configChanged = true;
        }
    }

    if ($configChanged) {
        $configWriter->set('defaultDashboardLayouts', $defaultLayouts);
    }
}

if ($configChanged) {
    $configWriter->save();
}

if ($prefUpdated === 0 && !$configChanged) {
    echo "\nATTENZIONE: nessuna preferenza/config con tab \"" . TAB_SOURCE . "\" trovata.\n";
    echo "Eseguire: php tools/duplica-report-appuntamenti-trimestre.php --diagnose\n";
}

echo "\nBackup: {$backupDir}\n";
echo "Report creati: {$created}, aggiornati: {$updated}, già presenti: {$skipped}\n";
echo "Preferenze utente aggiornate: {$prefUpdated}\n";
echo "Eseguire: php clear_cache.php && php rebuild.php\n";
echo "Poi Ctrl+F5 (o logout/login) sul tab Appuntamenti Ultimo Trimestre.\n";

if ($dashboardOnly) {
    echo "\nPasso 2 completato.\n";
}
