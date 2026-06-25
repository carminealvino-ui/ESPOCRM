<?php
/**
 * Helper condivisi per script dashboard/report (CRM KPI, Vendite Mese, …).
 */
declare(strict_types=1);

use Espo\Core\ApplicationUser;
use Espo\Core\Container;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

function fail(string $message): void
{
    fwrite(STDERR, "ERRORE: {$message}\n");
    exit(1);
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

function resolveRunUser(EntityManager $em, array $argv): Entity
{
    $requested = parseRunUserName($argv);

    if ($requested === 'system') {
        fail('resolveRunUser non supporta system. Usare setupRunUser().');
    }

    $user = $em->getRDBRepository('User')->where(['userName' => $requested])->findOne();

    if (!$user) {
        $user = $em->getRDBRepository('User')
            ->where(['type' => 'admin', 'isActive' => true])
            ->order('userName')
            ->findOne();
    }

    if (!$user) {
        fail('Utente non trovato: ' . $requested . ' (nessun admin attivo disponibile).');
    }

    if ($user->get('userName') !== $requested) {
        echo "Nota: --user={$requested} non trovato, uso {$user->get('userName')}\n";
    }

    return $user;
}

function setupRunUser(Container $container, EntityManager $em, array $argv): Entity
{
    $requested = parseRunUserName($argv);
    $appUser = $container->getByClass(ApplicationUser::class);

    if ($requested === 'system') {
        $appUser->setupSystemUser();
        echo "Utente esecuzione: system\n\n";

        return $em->getRDBRepository('User')->getById('system');
    }

    $user = resolveRunUser($em, $argv);

    $appUser->setUser($user);
    echo 'Utente esecuzione: ' . $user->get('userName') . "\n\n";

    return $user;
}

/**
 * @return array<int, array<string, mixed>>|null
 */
function normalizeDashboardTabs($tabs): ?array
{
    if (!is_array($tabs) || $tabs === []) {
        return null;
    }

    if (!array_is_list($tabs)) {
        return null;
    }

    $first = $tabs[0] ?? null;

    if (!is_array($first) || (!isset($first['name']) && !isset($first['layout']))) {
        return null;
    }

    return $tabs;
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
        return normalizeDashboardTabs($data->dashboardLayout);
    }

    if (is_array($data) && isset($data['dashboardLayout'])) {
        return normalizeDashboardTabs($data['dashboardLayout']);
    }

    try {
        $pdo = $em->getPDO();
        $stmt = $pdo->prepare('SELECT data FROM `preferences` WHERE id = ?');
        $stmt->execute([$pref->getId()]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && !empty($row['data'])) {
            $decoded = json_decode((string) $row['data'], true);

            if (is_array($decoded) && isset($decoded['dashboardLayout'])) {
                return normalizeDashboardTabs($decoded['dashboardLayout']);
            }
        }
    } catch (Throwable $e) {
        // ignore
    }

    return null;
}

/**
 * @param array<int, array<string, mixed>> $tabs
 */
function savePreferenceDashboardTabs(Entity $pref, EntityManager $em, array $tabs): void
{
    $pref->set('dashboardLayout', $tabs);

    $data = $pref->get('data');

    if (is_object($data)) {
        $data->dashboardLayout = $tabs;
        $pref->set('data', $data);
    } elseif (is_array($data)) {
        $data['dashboardLayout'] = $tabs;
        $pref->set('data', $data);
    } else {
        $pref->set('data', (object) ['dashboardLayout' => $tabs]);
    }
}

function backupJson(string $dir, string $filename, $data): void
{
    file_put_contents(
        $dir . '/' . $filename,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n"
    );
}

/**
 * @param mixed $tabs
 * @return string[]
 */
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
 * @return array<int, array<string, mixed>>|null
 */
function loadTabsFromBackupFile(string $path): ?array
{
    if (!is_file($path)) {
        return null;
    }

    $decoded = json_decode((string) file_get_contents($path), true);

    if (!is_array($decoded)) {
        return null;
    }

    if ($decoded !== [] && array_is_list($decoded)) {
        $first = $decoded[0] ?? null;

        if (is_array($first) && (isset($first['name']) || isset($first['layout']))) {
            return $decoded;
        }
    }

    if (isset($decoded['dashboardLayout'])) {
        return normalizeDashboardTabs($decoded['dashboardLayout']);
    }

    return null;
}

/**
 * @param array<int, array<string, mixed>> $layout
 * @param array<string, mixed> $dashlet
 * @return array{0: array<int, array<string, mixed>>, 1: bool}
 */
function mergeDashletIntoTabLayout(array $layout, array $dashlet, string $dashletName): array
{
    $changed = false;
    $filtered = [];

    foreach ($layout as $item) {
        if (!is_array($item)) {
            continue;
        }

        if (($item['name'] ?? '') === $dashletName) {
            $changed = true;
            continue;
        }

        $filtered[] = $item;
    }

    $maxBottom = 0;

    foreach ($filtered as $item) {
        $y = (int) ($item['y'] ?? 0);
        $height = (int) ($item['height'] ?? 2);
        $maxBottom = max($maxBottom, $y + $height);
    }

    if (!isset($dashlet['y'])) {
        $dashlet['y'] = $maxBottom;
    }

    if (!isset($dashlet['x'])) {
        $dashlet['x'] = 0;
    }

    $filtered[] = $dashlet;

    return [$filtered, true];
}

/**
 * Aggiunge il dashlet al tab esistente senza rimuovere gli altri.
 * Se il tab non esiste, lo crea.
 *
 * @param array<int, array<string, mixed>> $tabs
 * @param array<string, mixed> $dashlet
 * @return array{0: array<int, array<string, mixed>>, 1: bool, 2: string}
 */
function appendDashletToDashboardTab(array $tabs, string $tabName, array $dashlet, string $dashletName): array
{
    foreach ($tabs as $i => $tab) {
        if (!is_array($tab)) {
            continue;
        }

        if (($tab['name'] ?? '') !== $tabName) {
            continue;
        }

        $layout = is_array($tab['layout'] ?? null) ? $tab['layout'] : [];
        [$newLayout, $changed] = mergeDashletIntoTabLayout($layout, $dashlet, $dashletName);
        $tabs[$i]['layout'] = $newLayout;

        return [$tabs, $changed, 'merged'];
    }

    $dashlet['y'] = $dashlet['y'] ?? 0;
    $dashlet['x'] = $dashlet['x'] ?? 0;
    $tabs[] = [
        'name' => $tabName,
        'layout' => [$dashlet],
    ];

    return [$tabs, true, 'created'];
}

/**
 * @param array<int, array<string, mixed>> $tabs
 * @param array<int, array<string, mixed>> $layout
 * @return array{0: array<int, array<string, mixed>>, 1: bool}
 */
function appendDashboardTabIfMissing(array $tabs, string $tabName, array $layout): array
{
    foreach ($tabs as $tab) {
        if (is_array($tab) && ($tab['name'] ?? '') === $tabName) {
            return [$tabs, false];
        }
    }

    $tabs[] = [
        'name' => $tabName,
        'layout' => $layout,
    ];

    return [$tabs, true];
}

/**
 * @param array<int, array<string, mixed>> $tabs
 * @param array<int, array<string, mixed>> $layout
 * @return array{0: array<int, array<string, mixed>>, 1: bool}
 * @deprecated Prefer appendDashboardTabIfMissing — non sostituire tab esistenti.
 */
function upsertDashboardTab(array $tabs, string $tabName, array $layout): array
{
    return appendDashboardTabIfMissing($tabs, $tabName, $layout);
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
    }

    return $count;
}

/**
 * @param array<string, string> $reportIdMap
 * @param array<int, array<string, mixed>> $definitions
 * @return array<int, array<string, mixed>>
 */
function buildReportTabLayout(array $reportIdMap, array $definitions, string $prefix): array
{
    $layout = [];
    $col = 0;
    $row = 0;
    $width = 2;
    $height = 2;
    $colsPerRow = 2;

    foreach ($definitions as $definition) {
        $name = (string) ($definition['name'] ?? '');
        $reportId = $reportIdMap[$name] ?? null;

        if (!$reportId) {
            continue;
        }

        $title = $name;

        if (str_starts_with($title, $prefix)) {
            $title = substr($title, strlen($prefix));
        }

        $layout[] = [
            'id' => 'rpt-' . substr(md5($reportId), 0, 10),
            'name' => 'Report',
            'x' => $col * $width,
            'y' => $row * $height,
            'width' => $width,
            'height' => $height,
            'options' => [
                'reportId' => $reportId,
                'title' => $title,
            ],
        ];

        $col++;

        if ($col >= $colsPerRow) {
            $col = 0;
            $row++;
        }
    }

    return $layout;
}

/**
 * @param array<string, mixed> $definition
 */
function buildReportAttributes(array $definition): object
{
    $dateField = (string) ($definition['dateField'] ?? '');
    $dateFilterType = (string) ($definition['dateFilterType'] ?? 'currentMonth');
    $filtersData = buildDateFilterData($dateField, $dateFilterType, $definition['extraWhere'] ?? null);

    $type = (string) ($definition['type'] ?? 'List');

    $attributes = [
        'name' => (string) $definition['name'],
        'entityType' => (string) $definition['entityType'],
        'type' => $type,
        'columns' => array_values($definition['columns'] ?? []),
        'filtersData' => $filtersData,
        'filtersDataList' => [$filtersData],
    ];

    if ($type === 'Grid') {
        $attributes['groupBy'] = array_values($definition['columns'] ?? []);
        $attributes['sums'] = array_values($definition['sums'] ?? []);
    }

    if (!empty($definition['orderBy'])) {
        $attributes['orderBy'] = [$definition['orderBy']];
        $attributes['order'] = [$definition['order'] ?? 'desc'];
    }

    return (object) $attributes;
}

/**
 * @param array<string, mixed>|null $extraWhere
 * @return array<string, mixed>
 */
function buildDateFilterData(string $dateField, string $dateFilterType, ?array $extraWhere = null): array
{
    $value = [
        [
            'type' => $dateFilterType,
            'attribute' => $dateField,
            'field' => $dateField,
            'value' => $dateFilterType,
        ],
    ];

    if ($extraWhere) {
        foreach ($extraWhere as $attribute => $filterValue) {
            $value[] = [
                'type' => 'equals',
                'attribute' => $attribute,
                'value' => $filterValue,
            ];
        }
    }

    return [
        'type' => 'and',
        'value' => $value,
    ];
}
