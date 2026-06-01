#!/usr/bin/env php
<?php
/**
 * Rollback tab dashboard e (opzionale) report creati per trimestre / mese precedente.
 *
 *   php tools/rollback-dashboard-appuntamenti-periodo.php --user=carmine_alvino --dry-run
 *   php tools/rollback-dashboard-appuntamenti-periodo.php --user=carmine_alvino
 *   php tools/rollback-dashboard-appuntamenti-periodo.php --user=carmine_alvino --delete-reports --force
 *
 * In CRM: prima cliccare Annulla sulla finestra "Modifica Dashboard" (non Salva).
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
use Espo\Core\Record\DeleteParams;
use Espo\Core\Record\ServiceContainer as RecordServiceContainer;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

const ROLLBACK_VERSION = '2026-05-30-rollback';

const TABS_TO_REMOVE = [
    'Appuntamenti Ultimo Trimestre',
    'Appuntamenti Mese Precedente',
];

const REPORT_PREFIXES_TO_DELETE = [
    'Appuntamenti Ultimo Trimestre - ',
    'Appuntamenti Mese Precedente - ',
];

$argv = $GLOBALS['argv'] ?? [];
$dryRun = in_array('--dry-run', $argv, true);
$deleteReports = in_array('--delete-reports', $argv, true);
$force = in_array('--force', $argv, true);
$restoreFrom = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--restore-from=')) {
        $restoreFrom = substr($arg, 15);
    }
}

if ($deleteReports && !$force && !$dryRun) {
    fwrite(STDERR, "Per eliminare i report aggiungere --force\n");
    exit(1);
}

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

function setupRunUser($container, EntityManager $em, array $argv): void
{
    $userName = parseRunUserName($argv);
    $appUser = $container->getByClass(ApplicationUser::class);
    $user = $em->getRDBRepository('User')->where(['userName' => $userName])->findOne();

    if (!$user) {
        fail('Utente non trovato: ' . $userName);
    }

    $appUser->setUser($user);
    echo 'Utente: ' . $user->get('userName') . "\n";
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
 * @param mixed $value
 * @return array<int, array<string, mixed>>|null
 */
function findDashboardTabsArrayInBlob($value): ?array
{
    if (!is_array($value)) {
        return null;
    }

    if ($value !== [] && array_is_list($value)) {
        $first = $value[0] ?? null;

        if (is_array($first) && (isset($first['name']) || isset($first['layout']))) {
            return $value;
        }
    }

    foreach ($value as $child) {
        $found = findDashboardTabsArrayInBlob($child);

        if ($found !== null) {
            return $found;
        }
    }

    return null;
}

function getPreferenceDashboardTabs(Entity $pref, EntityManager $em): ?array
{
    $tabs = normalizeDashboardTabs($pref->get('dashboardLayout'));

    if ($tabs !== null && $tabs !== []) {
        return $tabs;
    }

    $blob = fetchPreferenceDataBlob($em, $pref->getId());

    if ($blob === null) {
        return null;
    }

    if (isset($blob['dashboardLayout'])) {
        $tabs = normalizeDashboardTabs($blob['dashboardLayout']);

        if ($tabs !== null && $tabs !== []) {
            return $tabs;
        }
    }

    return findDashboardTabsArrayInBlob($blob);
}

function savePreferenceDashboardTabs(Entity $pref, EntityManager $em, array $tabs): void
{
    $pref->set('dashboardLayout', $tabs);
    $blob = fetchPreferenceDataBlob($em, $pref->getId());

    if ($blob !== null) {
        $blob['dashboardLayout'] = $tabs;
        $pref->set('data', $blob);
    }
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

function tabShouldBeRemoved(string $name): bool
{
    if (in_array($name, TABS_TO_REMOVE, true)) {
        return true;
    }

    $lower = mb_strtolower($name);

    if (!str_contains($lower, 'appuntament')) {
        return false;
    }

    return str_contains($lower, 'ultimo trimestre')
        || (str_contains($lower, 'mese') && str_contains($lower, 'precedente'));
}

/**
 * @param array<int, array<string, mixed>> $tabs
 * @return array{0: array, 1: string[]}
 */
function removeTargetTabs(array $tabs): array
{
    $removed = [];
    $kept = [];

    foreach ($tabs as $tab) {
        if (!is_array($tab)) {
            continue;
        }

        $name = (string) ($tab['name'] ?? '');

        if (tabShouldBeRemoved($name)) {
            $removed[] = $name;
            continue;
        }

        $kept[] = $tab;
    }

    return [$kept, $removed];
}

$app = new Application();
$container = $app->getContainer();
/** @var EntityManager $em */
$em = $container->get('entityManager');

setupRunUser($container, $em, $argv);

echo 'Rollback v' . ROLLBACK_VERSION . ($dryRun ? " (DRY-RUN)\n\n" : "\n\n");

$runUser = $em->getRDBRepository('User')->where(['userName' => parseRunUserName($argv)])->findOne();
$pref = $em->getEntityById('Preferences', $runUser->getId());

if (!$pref) {
    fail('Preferenze non trovate');
}

$tabs = getPreferenceDashboardTabs($pref, $em);

if ($restoreFrom !== null && is_file($restoreFrom)) {
    $decoded = json_decode((string) file_get_contents($restoreFrom), true);

    if (!is_array($decoded)) {
        fail('Backup non valido: ' . $restoreFrom);
    }

    $tabs = $decoded;
    echo "Ripristino da: {$restoreFrom}\n";
}

if ($tabs === null || $tabs === []) {
    echo "Nessun layout in preferenze.\n";
} else {
    echo 'Tab attuali: ' . implode(' | ', listTabNames($tabs)) . "\n\n";
    [$newTabs, $removed] = removeTargetTabs($tabs);

    if ($removed === []) {
        echo "Nessun tab da rimuovere.\n";
    } else {
        echo 'Rimuovo: ' . implode(', ', $removed) . "\n";
        echo 'Restano: ' . implode(' | ', listTabNames($newTabs)) . "\n";

        if (!$dryRun) {
            $dir = $root . '/backup_dev/Appuntamento/rollback-' . date('Ymd-His');
            mkdir($dir, 0755, true);
            file_put_contents(
                $dir . '/preferences-before-rollback.json',
                json_encode($tabs, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );
            savePreferenceDashboardTabs($pref, $em, $newTabs);
            $em->saveEntity($pref);
            echo "Salvato. Backup: {$dir}\n";
        }
    }
}

if ($deleteReports) {
    echo "\n=== Report ===\n";
    /** @var RecordServiceContainer $recordServices */
    $recordServices = $container->getByClass(RecordServiceContainer::class);
    $reportService = $recordServices->get('Report');
    $n = 0;

    foreach ($em->getRDBRepository('Report')->where(['entityType' => 'Appuntamento'])->find() as $report) {
        $name = (string) $report->get('name');
        $ok = false;

        foreach (REPORT_PREFIXES_TO_DELETE as $prefix) {
            if (str_starts_with($name, $prefix)) {
                $ok = true;
                break;
            }
        }

        if (!$ok) {
            continue;
        }

        if ($dryRun) {
            echo "ELIMINEREI: {$name}\n";
            $n++;
            continue;
        }

        try {
            $reportService->delete($report->getId(), DeleteParams::create());
            echo "ELIMINATO: {$name}\n";
            $n++;
        } catch (Throwable $e) {
            echo "ERRORE {$name}: {$e->getMessage()}\n";
        }
    }

    echo "Totale report: {$n}\n";
}

echo "\nphp clear_cache.php — poi logout/login.\n";
echo "Se Modifica Dashboard è aperta: Annulla (non Salva).\n";
