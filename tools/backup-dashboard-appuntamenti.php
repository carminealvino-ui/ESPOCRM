#!/usr/bin/env php
<?php
/**
 * Backup preferenze dashboard (e opzionale elenco report Appuntamenti) prima di modifiche.
 *
 *   php tools/backup-dashboard-appuntamenti.php --user=carmine_alvino
 *   php tools/backup-dashboard-appuntamenti.php --user=carmine_alvino --with-reports
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
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

const BACKUP_VERSION = '2026-05-30';

$argv = $GLOBALS['argv'] ?? [];
$withReports = in_array('--with-reports', $argv, true);

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

    return 'admin';
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
    $pdo = $em->getPDO();
    $stmt = $pdo->prepare('SELECT data FROM `preferences` WHERE id = ?');
    $stmt->execute([$prefId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || !isset($row['data']) || $row['data'] === '') {
        return null;
    }

    $decoded = json_decode((string) $row['data'], true);

    return is_array($decoded) ? $decoded : null;
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
        return normalizeDashboardTabs($blob['dashboardLayout']);
    }

    return null;
}

$app = new Application();
$container = $app->getContainer();
/** @var EntityManager $em */
$em = $container->get('entityManager');

$userName = parseRunUserName($argv);
$user = $em->getRDBRepository('User')->where(['userName' => $userName])->findOne();

if (!$user) {
    fail('Utente non trovato: ' . $userName);
}

$container->getByClass(ApplicationUser::class)->setUser($user);

$pref = $em->getEntityById('Preferences', $user->getId());

if (!$pref) {
    fail('Preferenze non trovate per ' . $userName);
}

$stamp = date('Ymd-His');
$dir = $root . '/backup_dev/Appuntamento/dashboard-backup-' . $userName . '-' . $stamp;
mkdir($dir, 0755, true);

$tabs = getPreferenceDashboardTabs($pref, $em);
$blob = fetchPreferenceDataBlob($em, $pref->getId());

file_put_contents(
    $dir . '/dashboard-layout.json',
    json_encode($tabs ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL
);

file_put_contents(
    $dir . '/preferences-data-raw.json',
    json_encode($blob ?? new stdClass(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL
);

$meta = [
    'version' => BACKUP_VERSION,
    'userName' => $userName,
    'userId' => $user->getId(),
    'createdAt' => date('c'),
    'tabNames' => [],
];

if (is_array($tabs)) {
    foreach ($tabs as $tab) {
        if (is_array($tab) && isset($tab['name'])) {
            $meta['tabNames'][] = (string) $tab['name'];
        }
    }
}

file_put_contents(
    $dir . '/README-restore.txt',
    "Backup dashboard {$userName} del {$stamp}\n\n"
    . "Ripristino layout tab:\n"
    . "  php tools/rollback-dashboard-appuntamenti-periodo.php --user={$userName} "
    . "--restore-from={$dir}/dashboard-layout.json\n\n"
    . "Tab salvati: " . implode(' | ', $meta['tabNames']) . "\n"
);

file_put_contents(
    $dir . '/meta.json',
    json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL
);

echo "Backup dashboard v" . BACKUP_VERSION . "\n";
echo "Utente: {$userName}\n";
echo "Cartella: {$dir}\n";
echo 'Tab (' . count($meta['tabNames']) . '): ' . implode(' | ', $meta['tabNames']) . "\n";

if ($withReports) {
    $reports = [];
    foreach ($em->getRDBRepository('Report')->where(['entityType' => 'Appuntamento'])->order('name')->find() as $report) {
        $reports[] = [
            'id' => $report->getId(),
            'name' => $report->get('name'),
            'deleted' => $report->get('deleted'),
        ];
    }

    file_put_contents(
        $dir . '/reports-appuntamento.json',
        json_encode($reports, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL
    );

    echo 'Report Appuntamento esportati: ' . count($reports) . "\n";
}

echo "\nConservare questa cartella prima di rollback o altre modifiche.\n";
