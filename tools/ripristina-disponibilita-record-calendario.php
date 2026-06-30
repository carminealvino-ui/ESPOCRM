#!/usr/bin/env php
<?php
/**
 * Ripristina record Disponibilità per il calendario: risalva via hook SetName.
 * Non modifica SQL direttamente (a differenza del backfill precedente).
 *
 * Uso:
 *   php tools/ripristina-disponibilita-record-calendario.php --dry-run
 *   php tools/ripristina-disponibilita-record-calendario.php --from=2026-06-29 --to=2026-07-05
 */
declare(strict_types=1);

chdir(dirname(__DIR__));

require_once 'bootstrap.php';
require_once __DIR__ . '/disponibilita-date-helpers.php';

use Espo\Core\Application;

$application = new Application();
$application->setupSystemUser();

$entityManager = $application->getContainer()->get('entityManager');

$dryRun = in_array('--dry-run', $argv, true);
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

$timezone = new DateTimeZone('Europe/Rome');
$saved = 0;
$skipped = 0;

$collection = $entityManager
    ->getRDBRepository('Disponibilita')
    ->where(['deleted' => false])
    ->order('dateStart', 'ASC')
    ->find();

foreach ($collection as $entity) {
    $target = disponibilitaResolveTargetDateFromEntity($entity, $timezone);

    if ($target === null) {
        $skipped++;
        continue;
    }

    if ($dateFrom !== null && $target < $dateFrom) {
        $skipped++;
        continue;
    }

    if ($dateTo !== null && $target > $dateTo) {
        $skipped++;
        continue;
    }

    if ($dryRun) {
        fwrite(STDOUT, sprintf(
            "SAVE %s | %s | %s\n",
            $entity->getId(),
            $target,
            $entity->get('name') ?: '(no name)'
        ));
        $saved++;
        continue;
    }

    $entity->set('datadisponibilita', $target);
    $entityManager->saveEntity($entity);
    $saved++;
}

fwrite(STDOUT, sprintf(
    "Fatto. Risalvati: %d | Saltati: %d%s\n",
    $saved,
    $skipped,
    $dryRun ? ' (dry-run)' : ''
));
