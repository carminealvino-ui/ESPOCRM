#!/usr/bin/env php
<?php
/**
 * Allinea datadisponibilita = data (Europe/Rome) di dateStart sui record esistenti.
 *
 * Uso: php tools/backfill-disponibilita-data-da-inizio.php [--dry-run] [--verbose]
 */
declare(strict_types=1);

chdir(dirname(__DIR__));

require_once 'bootstrap.php';

use Espo\Core\Application;

$application = new Application();
$application->setupSystemUser();

$entityManager = $application->getContainer()->get('entityManager');

$dryRun = in_array('--dry-run', $argv, true);
$verbose = in_array('--verbose', $argv, true);

$timezone = new DateTimeZone('Europe/Rome');
$updated = 0;
$skipped = 0;

$collection = $entityManager
    ->getRDBRepository('Disponibilita')
    ->where([
        'dateStart!=' => null,
    ])
    ->find();

foreach ($collection as $entity) {
    $dateStart = $entity->get('dateStart');

    if (!$dateStart) {
        $skipped++;
        continue;
    }

    $dt = new DateTime($dateStart, new DateTimeZone('UTC'));
    $dt->setTimezone($timezone);
    $target = $dt->format('Y-m-d');
    $current = $entity->get('datadisponibilita');

    if ($current === $target) {
        $skipped++;
        continue;
    }

    if ($verbose || $dryRun) {
        fwrite(STDOUT, sprintf(
            "%s | %s -> %s | dateStart=%s\n",
            $entity->getId(),
            $current ?? '(null)',
            $target,
            $dateStart
        ));
    }

    if (!$dryRun) {
        $entity->set('datadisponibilita', $target);
        $entityManager->saveEntity($entity, ['skipHooks' => true, 'silent' => true]);
    }

    $updated++;
}

fwrite(STDOUT, sprintf(
    "Fatto. Aggiornati: %d | Invariati: %d%s\n",
    $updated,
    $skipped,
    $dryRun ? ' (dry-run)' : ''
));
