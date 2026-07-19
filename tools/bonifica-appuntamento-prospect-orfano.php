<?php

/**
 * Pulisce prospectId orfani (Prospect cancellato) che causano 500 su GET/edit.
 * Opzionale: collega Prospect dal Lead parent.
 *
 *   php tools/bonifica-appuntamento-prospect-orfano.php --dry-run
 *   php tools/bonifica-appuntamento-prospect-orfano.php
 *   php tools/bonifica-appuntamento-prospect-orfano.php 6900a442ee92bd824
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use Espo\Core\Application;

$dryRun = in_array('--dry-run', $argv ?? [], true);
$filter = null;

foreach ($argv ?? [] as $i => $arg) {
    if ($i === 0 || str_starts_with((string) $arg, '--')) {
        continue;
    }
    $filter = trim((string) $arg);
    break;
}

$app = new Application();
$app->setupSystemUser();
$em = $app->getContainer()->get('entityManager');

if ($filter) {
    $collection = $em->getRDBRepository('Appuntamento')
        ->where([
            'OR' => [
                ['id' => $filter],
                ['name*' => $filter],
            ],
        ])
        ->find();
} else {
    $collection = $em->getRDBRepository('Appuntamento')
        ->where(['prospectId!=' => null])
        ->find();
}

$fixed = 0;
$skipped = 0;

foreach ($collection as $entity) {
    $prospectId = trim((string) ($entity->get('prospectId') ?? ''));

    if ($prospectId === '') {
        $skipped++;
        continue;
    }

    $prospect = $em->getEntityById('Prospect', $prospectId);

    if ($prospect) {
        $skipped++;
        continue;
    }

    $newProspectId = null;
    $newProspectName = null;

    // Fallback: Lead.parent o leadId → prospect del lead
    $lead = null;
    if ($entity->get('parentType') === 'Lead' && $entity->get('parentId')) {
        $lead = $em->getEntityById('Lead', (string) $entity->get('parentId'));
    } elseif ($entity->get('leadId')) {
        $lead = $em->getEntityById('Lead', (string) $entity->get('leadId'));
    }

    if ($lead) {
        $fromLead = trim((string) ($lead->get('prospectId') ?? ''));
        if ($fromLead !== '') {
            $p2 = $em->getEntityById('Prospect', $fromLead);
            if ($p2) {
                $newProspectId = $p2->getId();
                $newProspectName = $p2->get('name');
            }
        }
    }

    echo ($dryRun ? 'DRY ' : 'OK  ')
        . $entity->getId()
        . ' | orfano=' . $prospectId
        . ' | name=' . $entity->get('name')
        . ($newProspectId
            ? " → relink {$newProspectId} ({$newProspectName})"
            : ' → clear prospectId')
        . PHP_EOL;

    if (!$dryRun) {
        if ($newProspectId) {
            $entity->set('prospectId', $newProspectId);
            $entity->set('prospectName', $newProspectName);
        } else {
            $entity->set('prospectId', null);
            $entity->set('prospectName', null);
        }

        $em->saveEntity($entity, [
            'silent' => true,
            'skipHooks' => true,
        ]);
    }

    $fixed++;
}

echo PHP_EOL . "Corretti={$fixed} ok={$skipped}" . ($dryRun ? ' (dry-run)' : '') . PHP_EOL;
