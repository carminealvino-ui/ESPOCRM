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
const SCRIPT_VERSION = '2026-05-30-layout-by-report';

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
 * @param array<int, array<string, mixed>>|null $sourceLayoutOverride
 * @return array{0: mixed, 1: bool, 2: int}
 */
function syncTrimestreTab($tabs, array $reportIdMap, EntityManager $em, ?array $sourceLayoutOverride = null): array
{
    if (!is_array($tabs)) {
        $tabs = [];
    }

    $sourceLayout = $sourceLayoutOverride ?? findTabLayoutByMatcher($tabs, 'tabNameIsSourceTab');

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

        if (tabNameIsTargetTab((string) ($tab['name'] ?? ''))) {
            $tabs[$i]['name'] = TAB_TARGET;
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

/**
 * @param mixed $tabs
 * @return array<int, array<string, mixed>>|null
 */
function normalizeDashboardTabs($tabs): ?array
{
    if (is_string($tabs) && $tabs !== '') {
        $decoded = json_decode($tabs, true);

        return is_array($decoded) ? $decoded : null;
    }

    return is_array($tabs) ? $tabs : null;
}

/**
 * @return array<string, mixed>|null
 */
function fetchPreferenceDataBlob(EntityManager $em, string $prefId): ?array
{
    try {
        $pdo = $em->getPDO();
        $stmt = $pdo->prepare('SELECT data FROM `preferences` WHERE id = ?');
        $stmt->execute([$prefId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !isset($row['data']) || $row['data'] === '') {
            return null;
        }

        $decoded = json_decode((string) $row['data'], true);

        return is_array($decoded) ? $decoded : null;
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * @return array<int, array<string, mixed>>|null
 */
function getPreferenceDashboardTabs(Entity $pref, EntityManager $em): ?array
{
    $tabs = normalizeDashboardTabs($pref->get('dashboardLayout'));

    if ($tabs !== null) {
        return $tabs;
    }

    $data = $pref->get('data');

    if (is_object($data) && isset($data->dashboardLayout)) {
        $tabs = normalizeDashboardTabs($data->dashboardLayout);

        if ($tabs !== null) {
            return $tabs;
        }
    }

    if (is_array($data) && isset($data['dashboardLayout'])) {
        $tabs = normalizeDashboardTabs($data['dashboardLayout']);

        if ($tabs !== null) {
            return $tabs;
        }
    }

    $blob = fetchPreferenceDataBlob($em, $pref->getId());

    if ($blob !== null && isset($blob['dashboardLayout'])) {
        return normalizeDashboardTabs($blob['dashboardLayout']);
    }

    return null;
}

/**
 * @return array<string, true>
 */
function buildMeseReportIdSet(EntityManager $em): array
{
    $set = [];

    foreach ($em->getRDBRepository('Report')->where(['entityType' => 'Appuntamento'])->find() as $report) {
        if (str_starts_with((string) $report->get('name'), SOURCE_PREFIX)) {
            $set[$report->getId()] = true;
        }
    }

    return $set;
}

/**
 * @param mixed $layout
 * @param array<string, true> $meseReportIds
 */
function countMeseReportDashletsInLayout($layout, array $meseReportIds): int
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
            $reportId = $item['options']['reportId'] ?? null;

            if (is_string($reportId) && isset($meseReportIds[$reportId])) {
                $count++;
            }
        }

        foreach ($item as $value) {
            if (is_array($value)) {
                $count += countMeseReportDashletsInLayout($value, $meseReportIds);
            }
        }
    }

    return $count;
}

/**
 * Tab con più dashlet Report "Appuntamenti Mese - …" (anche se il tab ha altro nome).
 *
 * @param array<int, array<string, mixed>> $tabs
 * @param array<string, true> $meseReportIds
 */
function findSourceTabLayoutByMeseReports(array $tabs, array $meseReportIds): ?array
{
    $bestLayout = null;
    $bestCount = 0;

    foreach ($tabs as $tab) {
        if (!is_array($tab)) {
            continue;
        }

        $layout = $tab['layout'] ?? null;

        if (!is_array($layout)) {
            continue;
        }

        $count = countMeseReportDashletsInLayout($layout, $meseReportIds);

        if ($count > $bestCount) {
            $bestCount = $count;
            $bestLayout = $layout;
        }
    }

    return $bestCount >= 4 ? $bestLayout : null;
}

function tabNameIsSourceTab(string $name): bool
{
    if ($name === TAB_SOURCE) {
        return true;
    }

    $lower = mb_strtolower($name);

    return str_contains($lower, 'appuntament')
        && str_contains($lower, 'mese')
        && !str_contains($lower, 'trimestre');
}

function tabNameIsTargetTab(string $name): bool
{
    if ($name === TAB_TARGET) {
        return true;
    }

    $lower = mb_strtolower($name);

    return str_contains($lower, 'appuntament')
        && str_contains($lower, 'trimestre');
}

/**
 * @param array<int, array<string, mixed>> $tabs
 */
function findTabLayoutByMatcher(array $tabs, callable $matcher): ?array
{
    foreach ($tabs as $tab) {
        if (!is_array($tab)) {
            continue;
        }

        $name = (string) ($tab['name'] ?? '');

        if ($matcher($name)) {
            $layout = $tab['layout'] ?? null;

            return is_array($layout) ? $layout : null;
        }
    }

    return null;
}

/**
 * Cerca il layout del tab "Appuntamenti Mese" (8 dashlet) in preferenze e config.
 *
 * @return array{0: array, 1: string, 2: int}|null
 */
function findBestSourceTabLayout(EntityManager $em, Config $config, ?string $preferUserId = null): ?array
{
    $best = null;
    $meseReportIds = buildMeseReportIdSet($em);

    $consider = function (array $tabs, string $label) use (&$best, $meseReportIds): void {
        $layout = findTabLayoutByMatcher($tabs, 'tabNameIsSourceTab');
        $suffix = '';

        if ($layout === null && $meseReportIds !== []) {
            $layout = findSourceTabLayoutByMeseReports($tabs, $meseReportIds);
            $suffix = ' [via report Mese]';
        }

        if ($layout === null) {
            return;
        }

        $count = countReportDashlets($layout);

        if ($best === null || $count > $best[2]) {
            $best = [$layout, $label . $suffix, $count];
        }
    };

    if ($preferUserId !== null) {
        $pref = $em->getEntityById('Preferences', $preferUserId);

        if ($pref) {
            $tabs = getPreferenceDashboardTabs($pref, $em);

            if ($tabs !== null) {
                $user = $em->getEntityById('User', $preferUserId);
                $userName = $user ? (string) $user->get('userName') : $preferUserId;
                $consider($tabs, 'preferenze:' . $userName);
            }
        }
    }

    foreach (iterateAllPreferences($em) as $pref) {
        $tabs = getPreferenceDashboardTabs($pref, $em);

        if ($tabs === null) {
            continue;
        }

        $user = $em->getEntityById('User', $pref->getId());
        $userName = $user ? (string) $user->get('userName') : $pref->getId();
        $consider($tabs, 'preferenze:' . $userName);
    }

    $cfgLayout = normalizeDashboardTabs($config->get('dashboardLayout'));

    if ($cfgLayout !== null) {
        $consider($cfgLayout, 'config:dashboardLayout');
    }

    $defaultLayouts = $config->get('defaultDashboardLayouts');

    if (is_array($defaultLayouts)) {
        foreach ($defaultLayouts as $layoutKey => $tabs) {
            $tabs = normalizeDashboardTabs($tabs);

            if ($tabs === null) {
                continue;
            }

            $consider($tabs, 'config:defaultDashboardLayouts[' . $layoutKey . ']');
        }
    }

    if ($em->getMetadata()->getDefs()->hasEntity('DashboardTemplate')) {
        foreach ($em->getRDBRepository('DashboardTemplate')->find() as $template) {
            $tabs = normalizeDashboardTabs($template->get('layout'));

            if ($tabs === null) {
                continue;
            }

            $consider($tabs, 'DashboardTemplate:' . (string) $template->get('name'));
        }
    }

    if ($preferUserId !== null) {
        $user = $em->getEntityById('User', $preferUserId);

        if ($user && $user->get('dashboardTemplateId')) {
            $template = $em->getEntityById('DashboardTemplate', $user->get('dashboardTemplateId'));

            if ($template) {
                $tabs = normalizeDashboardTabs($template->get('layout'));

                if ($tabs !== null) {
                    $consider($tabs, 'User.dashboardTemplate:' . (string) $template->get('name'));
                }
            }
        }
    }

    return $best;
}

/**
 * @return array<int, array<string, mixed>>
 */
function defaultDashboardTabsForUser(Config $config, Entity $user): array
{
    $defaultLayouts = $config->get('defaultDashboardLayouts');

    if (!is_array($defaultLayouts)) {
        return [];
    }

    $layoutKey = 'Standard';

    if ($user->get('type') === 'admin' && isset($defaultLayouts['Admin'])) {
        $layoutKey = 'Admin';
    }

    $tabs = normalizeDashboardTabs($defaultLayouts[$layoutKey] ?? $defaultLayouts['Standard'] ?? null);

    return $tabs ?? [];
}

/**
 * La tabella preferences non ha colonna deleted: non usare Repository::find().
 *
 * @return iterable<Entity>
 */
function iterateAllPreferences(EntityManager $em): iterable
{
    $pdo = $em->getPDO();
    $stmt = $pdo->query('SELECT id FROM `preferences`');

    if ($stmt === false) {
        return;
    }

    while ($id = $stmt->fetchColumn()) {
        $pref = $em->getEntityById('Preferences', $id);

        if ($pref) {
            yield $pref;
        }
    }
}

function diagnosePreferencesScan(EntityManager $em): void
{
    $found = [];

    try {
        $pdo = $em->getPDO();
        $stmt = $pdo->query('SELECT id, data FROM `preferences`');

        if ($stmt !== false) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $blob = (string) ($row['data'] ?? '');

                if (
                    str_contains($blob, 'Appuntamenti Mese')
                    || str_contains($blob, 'Appuntamenti Ultimo Trimestre')
                ) {
                    $found[] = (string) $row['id'];
                }
            }
        }
    } catch (Throwable $e) {
        echo 'Scan preferences (fallback ORM): ' . $e->getMessage() . "\n";

        foreach (iterateAllPreferences($em) as $pref) {
            $tabs = getPreferenceDashboardTabs($pref, $em);

            if ($tabs === null) {
                continue;
            }

            $names = implode(' ', listTabNames($tabs));

            if (str_contains($names, 'Appuntamenti')) {
                $found[] = $pref->getId();
            }
        }
    }

    if ($found === []) {
        echo "Preferences: nessun utente con tab Appuntamenti nel JSON.\n";
    } else {
        echo 'Preferences con Appuntamenti nel layout (id utente): ' . implode(', ', array_slice($found, 0, 15)) . "\n";
    }
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

    diagnosePreferencesScan($em);

    $n = 0;
    $runUserName = parseRunUserName($argv);
    $runUser = $em->getRDBRepository('User')->where(['userName' => $runUserName])->findOne();
    $runUserId = $runUser ? $runUser->getId() : null;

    $meseReportIds = buildMeseReportIdSet($em);

    foreach (iterateAllPreferences($em) as $pref) {
        $tabs = getPreferenceDashboardTabs($pref, $em);

        if ($tabs === null || $tabs === []) {
            continue;
        }

        $names = listTabNames($tabs);
        $hasMese = findTabLayoutByMatcher($tabs, 'tabNameIsSourceTab') !== null
            || findSourceTabLayoutByMeseReports($tabs, $meseReportIds) !== null;
        $hasTrim = findTabLayoutByMatcher($tabs, 'tabNameIsTargetTab') !== null;

        if (!$hasMese && !$hasTrim) {
            continue;
        }

        $n++;
        $userId = $pref->getId();
        $user = $em->getEntityById('User', $userId);
        $userName = $user ? $user->get('userName') : $userId;
        $marker = ($runUserId && $userId === $runUserId) ? ' ← utente --user' : '';

        echo "User: {$userName}{$marker}\n";
        echo '  Tab: ' . implode(' | ', $names) . "\n";

        foreach ($tabs as $tab) {
            if (!is_array($tab)) {
                continue;
            }

            $name = (string) ($tab['name'] ?? '');

            if (tabNameIsSourceTab($name) || tabNameIsTargetTab($name)) {
                $cnt = countReportDashlets($tab['layout'] ?? []);
                echo "  {$name}: {$cnt} dashlet Report\n";
            }
        }

        echo "\n";
    }

    echo "Preferenze con tab Mese/Trimestre (match fuzzy): {$n}\n";

    $cfgLayout = normalizeDashboardTabs($config->get('dashboardLayout'));
    if ($cfgLayout !== null) {
        echo "\nConfig dashboardLayout tab: " . implode(' | ', listTabNames($cfgLayout)) . "\n";
        $mese = countReportDashlets(findTabLayoutByMatcher($cfgLayout, 'tabNameIsSourceTab') ?? []);
        $trim = countReportDashlets(findTabLayoutByMatcher($cfgLayout, 'tabNameIsTargetTab') ?? []);
        echo "  Mese (config): {$mese} dashlet Report | Trimestre (config): {$trim}\n";
    }

    $defaultLayouts = $config->get('defaultDashboardLayouts');
    if (is_array($defaultLayouts)) {
        echo "Config defaultDashboardLayouts chiavi: " . implode(', ', array_keys($defaultLayouts)) . "\n";

        foreach ($defaultLayouts as $layoutKey => $tabs) {
            $tabs = normalizeDashboardTabs($tabs);

            if ($tabs === null) {
                continue;
            }

            $mese = countReportDashlets(findTabLayoutByMatcher($tabs, 'tabNameIsSourceTab') ?? []);
            $trim = countReportDashlets(findTabLayoutByMatcher($tabs, 'tabNameIsTargetTab') ?? []);

            if ($mese > 0 || $trim > 0) {
                echo "  [{$layoutKey}] Mese: {$mese} dashlet | Trimestre: {$trim} dashlet\n";
            }
        }
    }

    $best = findBestSourceTabLayout($em, $config, $runUserId);

    if ($runUserId !== null) {
        $pref = $em->getEntityById('Preferences', $runUserId);
        $tabs = $pref ? getPreferenceDashboardTabs($pref, $em) : null;

        if ($tabs !== null && $tabs !== []) {
            echo "\nTab nel tuo utente (--user): " . implode(' | ', listTabNames($tabs)) . "\n";

            foreach ($tabs as $tab) {
                if (!is_array($tab)) {
                    continue;
                }

                $name = (string) ($tab['name'] ?? '');
                $cnt = countReportDashlets($tab['layout'] ?? []);
                $meseCnt = countMeseReportDashletsInLayout($tab['layout'] ?? [], $meseReportIds);
                echo "  · {$name}: {$cnt} dashlet Report ({$meseCnt} report \"Appuntamenti Mese\")\n";
            }
        }
    }

    if ($best !== null) {
        echo "\nMiglior sorgente trovata: {$best[1]} con {$best[2]} dashlet Report\n";
    } else {
        echo "\nNessun layout sorgente \"Appuntamenti Mese\" trovato in DB/config.\n";
        echo "Aprire il tab Appuntamenti Mese in CRM, verificare 8 widget, poi Salva dashboard.\n";
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
$runUserName = parseRunUserName($argv);
$runUser = $em->getRDBRepository('User')->where(['userName' => $runUserName])->findOne();

if (!$runUser) {
    fail('Utente non trovato: ' . $runUserName);
}

$sourceFound = findBestSourceTabLayout($em, $config, $runUser->getId());

if ($sourceFound === null) {
    echo "\nERRORE: nessun tab \"" . TAB_SOURCE . "\" con dashlet trovato.\n";
    echo "Eseguire: php tools/duplica-report-appuntamenti-trimestre.php --diagnose --user={$runUserName}\n";
    echo "In CRM: aprire tab Appuntamenti Mese (8 report), poi rieseguire --dashboard-only.\n";
    exit(1);
}

[$sourceLayout, $sourceLabel, $sourceDashletCount] = $sourceFound;
echo "Layout sorgente: {$sourceLabel} ({$sourceDashletCount} dashlet Report)\n\n";

$applyToUser = function (Entity $user) use (
    $em,
    $config,
    $reportIdMap,
    $sourceLayout,
    $sourceLabel,
    $backupDir,
    &$prefUpdated
): void {
    $pref = $em->getEntityById('Preferences', $user->getId());

    if (!$pref) {
        echo "SKIP: nessuna preferenza per {$user->get('userName')}\n";

        return;
    }

    $tabs = getPreferenceDashboardTabs($pref, $em);

    if ($tabs === null || $tabs === []) {
        $tabs = defaultDashboardTabsForUser($config, $user);
        echo "  (dashboard da default, preferenze vuote)\n";
    }

    backupJson($backupDir, 'preferences-' . $pref->getId() . '-before.json', $tabs);

    [$newTabs, $changed, $dashletCount] = syncTrimestreTab($tabs, $reportIdMap, $em, $sourceLayout);

    if (!$changed) {
        echo "NESSUNA MODIFICA: {$user->get('userName')}\n";

        return;
    }

    $pref->set('dashboardLayout', $newTabs);
    $em->saveEntity($pref);
    $prefUpdated++;

    echo "PREFERENZE: {$user->get('userName')} → tab \"" . TAB_TARGET . "\" con {$dashletCount} dashlet (da {$sourceLabel})\n";
};

$applyToUser($runUser);

foreach (iterateAllPreferences($em) as $pref) {
    if ($pref->getId() === $runUser->getId()) {
        continue;
    }

    $tabs = getPreferenceDashboardTabs($pref, $em);

    if ($tabs === null) {
        continue;
    }

    $hasSource = findTabLayoutByMatcher($tabs, 'tabNameIsSourceTab') !== null
        || findSourceTabLayoutByMeseReports($tabs, buildMeseReportIdSet($em)) !== null;

    if (!$hasSource) {
        continue;
    }

    $user = $em->getEntityById('User', $pref->getId());

    if (!$user) {
        continue;
    }

    $applyToUser($user);
}

$configWriter = $container->get('configWriter');
$configChanged = false;

foreach (['dashboardLayout'] as $configKey) {
    $tabs = normalizeDashboardTabs($config->get($configKey));

    if ($tabs === null || findTabLayoutByMatcher($tabs, 'tabNameIsSourceTab') === null) {
        continue;
    }

    backupJson($backupDir, 'config-' . $configKey . '-before.json', $tabs);

    [$newTabs, $changed, $dashletCount] = syncTrimestreTab($tabs, $reportIdMap, $em, $sourceLayout);

    if ($changed) {
        $configWriter->set($configKey, $newTabs);
        $configChanged = true;
        echo "CONFIG {$configKey}: tab \"" . TAB_TARGET . "\" con {$dashletCount} dashlet Report\n";
    }
}

$defaultLayouts = $config->get('defaultDashboardLayouts');

if (is_array($defaultLayouts)) {
    foreach ($defaultLayouts as $layoutKey => $tabs) {
        $tabs = normalizeDashboardTabs($tabs);

        if ($tabs === null || findTabLayoutByMatcher($tabs, 'tabNameIsSourceTab') === null) {
            continue;
        }

        backupJson($backupDir, 'config-defaultDashboardLayouts-' . $layoutKey . '-before.json', $tabs);

        [$newTabs, $changed, $dashletCount] = syncTrimestreTab($tabs, $reportIdMap, $em, $sourceLayout);

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
