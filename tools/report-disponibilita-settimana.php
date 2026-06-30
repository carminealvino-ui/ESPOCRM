#!/usr/bin/env php
<?php
/**
 * Report Disponibilità per intervallo date (debug calendario).
 *
 * Uso:
 *   php tools/report-disponibilita-settimana.php --from=2026-06-29 --to=2026-07-05
 */
declare(strict_types=1);

chdir(dirname(__DIR__));

require_once 'bootstrap.php';

use Espo\Core\Application;

$application = new Application();
$application->setupSystemUser();

$pdo = $application->getContainer()->get('entityManager')->getPDO();

$dateFrom = null;
$dateTo = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--from=')) {
        $dateFrom = substr($arg, 7);
    }

    if (str_starts_with($arg, '--to=')) {
        $dateTo = substr($arg, 5);
    }
}

if ($dateFrom === null || $dateTo === null) {
    fwrite(STDERR, "Specificare --from=YYYY-MM-DD --to=YYYY-MM-DD\n");
    exit(1);
}

$sql = 'SELECT id, name, datadisponibilita, date_start_date, orario_inizio, orario_fine, is_all_day, color
        FROM disponibilita
        WHERE deleted = 0
          AND date_start_date >= :from
          AND date_start_date <= :to
        ORDER BY date_start_date ASC';

$stmt = $pdo->prepare($sql);
$stmt->execute(['from' => $dateFrom, 'to' => $dateTo]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

fwrite(STDOUT, sprintf("=== Disponibilità %s → %s (%d record) ===\n", $dateFrom, $dateTo, count($rows)));

foreach ($rows as $row) {
    fwrite(STDOUT, sprintf(
        "%s | %s | allDay=%s | color=%s | orario=%s / %s\n",
        $row['date_start_date'] ?? '?',
        $row['name'] ?? '(no name)',
        $row['is_all_day'] ?? '?',
        $row['color'] ?? '(null)',
        $row['orario_inizio'] ?? '(null)',
        $row['orario_fine'] ?? '(null)'
    ));
}
