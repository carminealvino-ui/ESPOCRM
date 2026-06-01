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
 *   php tools/duplica-report-appuntamenti-trimestre.php --fix-filters-only       # corregge filtri data (lastQuarter)
 *   php tools/duplica-report-appuntamenti-trimestre.php --diagnose
 *   php tools/duplica-report-appuntamenti-trimestre.php --reports-only --force --user=admin
 *
 * Usa lo stesso meccanismo del pulsante Duplica in CRM (getDuplicateAttributes + create).
 */
declare(strict_types=1);

$root = getenv('CRM_ROOT') ?: getcwd();

if (!is_file($root . '/bootstrap.php')) {
    fwrite(STDERR, "Eseguire da root CRM (bootstrap.php).\n");
    exit(1);
}

require_once $root . '/bootstrap.php';

use Espo\Core\Application;
use Espo\Core\ApplicationUser;
use Espo\Core\Container;
use Espo\Core\Record\CreateParams;
use Espo\Core\Record\DeleteParams;
use Espo\Core\Record\ServiceContainer as RecordServiceContainer;
use Espo\Core\Utils\Config;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

const SOURCE_PREFIX = 'Appuntamenti Mese - ';
const TARGET_PREFIX = 'Appuntamenti Ultimo Trimestre - ';
const TAB_SOURCE = 'Appuntamenti Mese';
const TAB_TARGET = 'Appuntamenti Ultimo Trimestre';
const SCRIPT_VERSION = '2026-05-29-lastQuarter';

/** Solo sul campo JSON "type" del filtro data (non su altri campi). */
const DATE_FILTER_TYPE_MAP = [
    'currentMonth' => 'lastQuarter',
    'thisMonth' => 'lastQuarter',
    'nextMonth' => 'lastQuarter',
    'previousQuarter' => 'lastQuarter',
];

$argv = $GLOBALS['argv'] ?? [];
$dryRun = in_array('--dry-run', $argv, true);
$diagnose = in_array('--diagnose', $argv, true);
$force = in_array('--force', $argv, true);
$reportsOnly = in_array('--reports-only', $argv, true);
$dashboardOnly = in_array('--dashboard-only', $argv, true);
$verbose = in_array('--verbose', $argv, true);
$fixFiltersOnly = in_array('--fix-filters-only', $argv, true);

if ($reportsOnly && $dashboardOnly) {
    fwrite(STDERR, "Usare solo uno tra --reports-only e --dashboard-only.\n");
    exit(1);
}

if ($fixFiltersOnly && ($reportsOnly || $dashboardOnly)) {
    fwrite(STDERR, "--fix-filters-only non va combinato con --reports-only o --dashboard-only.\n");
    exit(1);
}

$app = new Application();
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
 * Converte filtro mese → ultimo trimestre (tipo Espo: lastQuarter, non previousQuarter).
 *
 * @param mixed $data
 * @return mixed
 */
function mapDateFilterValues($data, ?string $parentKey = null)
{
    if (is_string($data)) {
        if ($parentKey === 'type' && isset(DATE_FILTER_TYPE_MAP[$data])) {
            return DATE_FILTER_TYPE_MAP[$data];
        }

        return $data;
    }

    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $childKey = is_string($key) ? $key : $parentKey;
            $data[$key] = mapDateFilterValues($value, $childKey);
        }

        return $data;
    }

    if (is_object($data)) {
        foreach (get_object_vars($data) as $key => $value) {
            $data->$key = mapDateFilterValues($value, $key);
        }
    }

    return $data;
}

function applyDateFilterMapToReport(Entity $report): void
{
    foreach (['filtersData', 'filtersDataList', 'filters', 'data'] as $attribute) {
        if (!$report->has($attribute)) {
            continue;
        }

        $value = $report->get($attribute);

        if ($value === null || $value === [] || $value === (object) []) {
            continue;
        }

        $report->set($attribute, mapDateFilterValues($value));
    }
}

function parseRunUserName(array $argv): string
{
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--user=')) {
            return substr($arg, 7);
        }
    }

    $fromEnv = getenv('ESPO_USER');

    return is_string($fromEnv) && $fromEnv !== '' ? $fromEnv : 'admin';
}

function setupRunUser(Container $container, EntityManager $em, array $argv): void
{
    $userName = parseRunUserName($argv);
    $appUser = $container->getByClass(ApplicationUser::class);

    if ($userName === 'system') {
        $appUser->setupSystemUser();
        echo "Utente esecuzione: system\n\n";

        return;
    }

    $user = $em->getRDBRepository('User')->where(['userName' => $userName])->findOne();

    if (!$user) {
        $user = $em->getRDBRepository('User')
            ->where(['type' => 'admin', 'isActive' => true])
            ->order('userName')
            ->findOne();
    }

    if (!$user) {
        fail('Nessun utente admin trovato. Usare --user=NOMEUTENTE');
    }

    $appUser->setUser($user);
    echo 'Utente esecuzione: ' . $user->get('userName') . "\n\n";
}

setupRunUser($container, $em, $argv);

echo 'Script duplica-report-appuntamenti-trimestre v' . SCRIPT_VERSION . "\n";

if ($fixFiltersOnly) {
    echo "Modalità: --fix-filters-only\n\n";
} elseif ($diagnose) {
    echo "Modalità: --diagnose\n\n";
} elseif ($dashboardOnly) {
    echo "Modalità: --dashboard-only\n\n";
} elseif ($reportsOnly) {
    echo "Modalità: --reports-only\n\n";
}

function reportJsonContainsFilterType($data, string $needle): bool
{
    if (is_string($data)) {
        return $data === $needle;
    }

    if (is_array($data) || is_object($data)) {
        foreach ($data as $value) {
            if (reportJsonContainsFilterType($value, $needle)) {
                return true;
            }
        }
    }

    return false;
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

/** @var RecordServiceContainer $recordServiceContainer */
$recordServiceContainer = $container->getByClass(RecordServiceContainer::class);
$reportService = $recordServiceContainer->get('Report');

if ($fixFiltersOnly) {
    echo "Correzione filtri data su report \"" . TARGET_PREFIX . "*\" (tipo lastQuarter).\n\n";

    $fixed = 0;
    $unchanged = 0;
    $errors = 0;

    foreach ($reportRepo->where(['entityType' => 'Appuntamento'])->find() as $report) {
        $name = (string) $report->get('name');

        if (!str_starts_with($name, TARGET_PREFIX)) {
            continue;
        }

        $hadBadType = reportJsonContainsFilterType($report->get('filtersData'), 'previousQuarter')
            || reportJsonContainsFilterType($report->get('filtersDataList'), 'previousQuarter')
            || reportJsonContainsFilterType($report->get('filters'), 'previousQuarter')
            || reportJsonContainsFilterType($report->get('data'), 'previousQuarter')
            || reportJsonContainsFilterType($report->get('filtersData'), 'currentMonth')
            || reportJsonContainsFilterType($report->get('filtersDataList'), 'currentMonth');

        applyDateFilterMapToReport($report);

        if ($dryRun) {
            echo ($hadBadType ? 'AGGIORNA' : 'OK') . ": {$name}\n";
            continue;
        }

        if (!$hadBadType) {
            $unchanged++;
            echo "OK (già corretto): {$name}\n";
            continue;
        }

        try {
            $em->saveEntity($report);
            $fixed++;
            echo "FILTRI AGGIORNATI: {$name}\n";
        } catch (Throwable $e) {
            $errors++;
            echo "ERRORE {$name}: {$e->getMessage()}\n";
        }
    }

    echo "\nReport corretti: {$fixed}, già ok: {$unchanged}\n";
    echo "Eseguire: php clear_cache.php\n";
    echo "Poi aprire un report trimestre in CRM e verificare che non compaia l'errore previousQuarter.\n";
    exit($errors > 0 ? 1 : 0);
}

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
            echo ($existing ? 'RIGENERA' : 'DUPLICA') . ": {$sourceName}\n  -> {$targetName}\n";
            continue;
        }

        if ($existing && $force) {
            try {
                $reportService->delete($existing->getId(), DeleteParams::create());
                echo "ELIMINATO (rigenero): {$targetName}\n";
            } catch (Throwable $e) {
                $em->removeEntity($existing);
                echo "ELIMINATO (repo): {$targetName}\n";
            }
        }

        try {
            $attributes = $reportService->getDuplicateAttributes($source->getId());
            $attributes->name = $targetName;
            $attributes = mapDateFilterValues($attributes);

            $createParams = CreateParams::create()
                ->withDuplicateSourceId($source->getId())
                ->withSkipDuplicateCheck(true);

            $createResult = $reportService->create($attributes, $createParams);
            $newEntity = $createResult instanceof Entity
                ? $createResult
                : $createResult->getEntity();
            $reportIdMap[$source->getId()] = $newEntity->getId();
            $created++;
            echo "DUPLICATO (come CRM): {$targetName} (id={$newEntity->getId()})\n";
        } catch (Throwable $e) {
            echo "ERRORE DUPLICA {$targetName}: {$e->getMessage()}\n";
            if ($verbose) {
                echo $e->getTraceAsString() . "\n";
            }
        }
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

function fail(string $message): void
{
    fwrite(STDERR, "ERRORE: {$message}\n");
    exit(1);
}
