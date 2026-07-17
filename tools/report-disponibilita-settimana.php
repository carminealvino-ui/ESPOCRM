#!/usr/bin/env php
<?php
/**
 * Report Disponibilità per intervallo date (via EntityManager, come la UI).
 *
 * Uso:
 *   php tools/report-disponibilita-settimana.php --from=2026-06-29 --to=2026-07-05
 */
declare(strict_types=1);

chdir(dirname(__DIR__));

require_once 'bootstrap.php';
require_once __DIR__ . '/disponibilita-date-helpers.php';

use Espo\Core\Application;

$application = new Application();
$application->setupSystemUser();

$entityManager = $application->getContainer()->get('entityManager');

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

$timezone = new DateTimeZone('Europe/Rome');
$rows = [];

$collection = $entityManager
    ->getRDBRepository('Disponibilita')
    ->where(['deleted' => false])
    ->order('dateStart', 'ASC')
    ->find();

foreach ($collection as $entity) {
    $target = disponibilitaResolveTargetDateFromEntity($entity, $timezone);

    if ($target === null || $target < $dateFrom || $target > $dateTo) {
        continue;
    }

    $rows[] = $entity;
}

fwrite(STDOUT, sprintf(
    "=== Disponibilità %s → %s (%d record) ===\n",
    $dateFrom,
    $dateTo,
    count($rows)
));

foreach ($rows as $entity) {
    fwrite(STDOUT, sprintf(
        "%s | %s | allDay=%s | color=%s\n",
        disponibilitaResolveTargetDateFromEntity($entity, $timezone),
        $entity->get('name') ?: '(no name)',
        $entity->get('isAllDay') ? '1' : '0',
        $entity->get('color') ?: '(null)'
    ));
}
