#!/usr/bin/env php
<?php
/**
 * Ripara Disponibilità non visibili in calendario (isAllDay/dateStartDate fuori sync).
 *
 * Uso:
 *   php tools/fix-disponibilita-calendario-display.php --dry-run
 *   php tools/fix-disponibilita-calendario-display.php
 *   php tools/fix-disponibilita-calendario-display.php --id=<uuid>
 */
declare(strict_types=1);

chdir(dirname(__DIR__));

require_once 'bootstrap.php';

use Espo\Core\Application;

$application = new Application();
$application->setupSystemUser();

$entityManager = $application->getContainer()->get('entityManager');
$dryRun = in_array('--dry-run', $argv, true);
$idFilter = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--id=')) {
        $idFilter = substr($arg, 5);
    }
}

$where = ['deleted' => false];

if ($idFilter) {
    $where['id'] = $idFilter;
}

$collection = $entityManager
    ->getRDBRepository('Disponibilita')
    ->where($where)
    ->find();

$fixed = 0;
$skipped = 0;

foreach ($collection as $entity) {
    $beforeAllDay = (bool) $entity->get('isAllDay');
    $beforeStartDate = (string) ($entity->get('dateStartDate') ?: '');
    $beforeData = (string) ($entity->get('datadisponibilita') ?: '');

    if ($dryRun) {
        if (!$beforeAllDay || $beforeStartDate === '' || $beforeStartDate !== $beforeData) {
            fwrite(STDOUT, sprintf(
                "FIX %s | %s | allDay=%s startDate=%s data=%s\n",
                $entity->getId(),
                $entity->get('name'),
                $beforeAllDay ? '1' : '0',
                $beforeStartDate ?: '(null)',
                $beforeData ?: '(null)'
            ));
            $fixed++;
        } else {
            $skipped++;
        }

        continue;
    }

    $entityManager->saveEntity($entity, ['skipHooks' => false]);

    $afterAllDay = (bool) $entity->get('isAllDay');
    $afterStartDate = (string) ($entity->get('dateStartDate') ?: '');

    if (!$beforeAllDay || $beforeStartDate !== $afterStartDate || !$beforeAllDay && $afterAllDay) {
        fwrite(STDOUT, sprintf(
            "OK %s | %s | allDay %s->%s | startDate %s->%s\n",
            $entity->getId(),
            $entity->get('name'),
            $beforeAllDay ? '1' : '0',
            $afterAllDay ? '1' : '0',
            $beforeStartDate ?: '(null)',
            $afterStartDate ?: '(null)'
        ));
        $fixed++;
    } else {
        $skipped++;
    }
}

fwrite(STDOUT, sprintf(
    "Fatto. Riparati: %d | Invariati: %d%s\n",
    $fixed,
    $skipped,
    $dryRun ? ' (dry-run)' : ''
));
