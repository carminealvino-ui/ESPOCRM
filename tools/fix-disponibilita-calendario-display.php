#!/usr/bin/env php
<?php
/**
 * Ripara Disponibilità per il calendario (usa EntityManager come la UI).
 *
 * Uso:
 *   php tools/fix-disponibilita-calendario-display.php --dry-run
 *   php tools/fix-disponibilita-calendario-display.php
 *   php tools/fix-disponibilita-calendario-display.php --from=2026-06-29 --to=2026-07-05
 *   php tools/fix-disponibilita-calendario-display.php --verbose
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
$verbose = in_array('--verbose', $argv, true);
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
$fixed = 0;
$skipped = 0;
$emptyRecords = 0;
$total = 0;

$collection = $entityManager
    ->getRDBRepository('Disponibilita')
    ->where(['deleted' => false])
    ->order('dateStart', 'DESC')
    ->find();

foreach ($collection as $entity) {
    $total++;
    $target = disponibilitaResolveTargetDateFromEntity($entity, $timezone);

    if ($target === null) {
        $emptyRecords++;

        if ($verbose) {
            fwrite(STDOUT, sprintf(
                "VUOTO %s | %s | %s\n",
                $entity->getId(),
                $entity->get('name') ?: '(no name)',
                disponibilitaDescribeEntityFields($entity)
            ));
        }

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

    $needsFix = !(bool) $entity->get('isAllDay')
        || disponibilitaNormalizeDate($entity->get('datadisponibilita')) !== $target
        || disponibilitaNormalizeDate($entity->get('dateStartDate')) !== $target
        || !disponibilitaOrarioMatchesTarget($entity->get('orarioInizio'), $target, $timezone)
        || !disponibilitaOrarioMatchesTarget($entity->get('orarioFine'), $target, $timezone)
        || trim((string) ($entity->get('name') ?: '')) === '';

    if (!$needsFix) {
        $skipped++;
        continue;
    }

    if ($dryRun) {
        fwrite(STDOUT, sprintf(
            "FIX %s | %s -> %s | %s\n",
            $entity->getId(),
            disponibilitaNormalizeDate($entity->get('dateStartDate')) ?? '(null)',
            $target,
            $entity->get('name') ?: '(no name)'
        ));
        $fixed++;
        continue;
    }

    $entity->set('datadisponibilita', $target);
    $entityManager->saveEntity($entity);

    if ($verbose) {
        fwrite(STDOUT, sprintf(
            "OK %s | %s | %s | allDay=%s\n",
            $entity->getId(),
            $entity->get('dateStartDate'),
            $entity->get('name'),
            $entity->get('isAllDay') ? '1' : '0'
        ));
    }

    $fixed++;
}

fwrite(STDOUT, sprintf(
    "Fatto. Totale: %d | Già ok: %d | Riparati: %d | Record vuoti (ignorati): %d%s\n",
    $total,
    $skipped,
    $fixed,
    $emptyRecords,
    $dryRun ? ' (dry-run)' : ''
));

if ($emptyRecords > 0 && !$verbose) {
    fwrite(STDOUT, "Suggerimento: --verbose per elencare i record vuoti (non sono quelli ARIEL in elenco).\n");
}
