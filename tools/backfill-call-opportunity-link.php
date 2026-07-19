<?php

/**
 * Backfill opportunityId sui Contatti Telefonici (richiami).
 *
 *   php tools/backfill-call-opportunity-link.php --dry-run
 *   php tools/backfill-call-opportunity-link.php
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\CallOpportunityLinker;

$dryRun = in_array('--dry-run', $argv ?? [], true);

$app = new Application();
$app->setupSystemUser();
$em = $app->getContainer()->get('entityManager');
$linker = new CallOpportunityLinker($em);

$collection = $em
    ->getRDBRepository('Call')
    ->where(['opportunityId' => null])
    ->order('createdAt', 'DESC')
    ->find();

$updated = 0;
$skipped = 0;

foreach ($collection as $call) {
    $opportunityId = $linker->resolveOpportunityId($call);

    if (!$opportunityId) {
        $skipped++;
        continue;
    }

    $opportunity = $em->getEntityById('Opportunity', $opportunityId);

    if (!$opportunity) {
        $skipped++;
        continue;
    }

    echo ($dryRun ? 'DRY ' : 'OK ')
        . $call->getId()
        . ' -> '
        . $opportunityId
        . ' '
        . $opportunity->get('name')
        . PHP_EOL;

    if (!$dryRun) {
        $call->set('opportunityId', $opportunityId);
        $call->set('opportunityName', $opportunity->get('name'));
        $em->saveEntity($call, [
            'silent' => true,
            'skipAcl' => true,
            'skipHooks' => true,
        ]);
    }

    $updated++;
}

echo PHP_EOL;
echo ($dryRun ? 'DRY-RUN ' : '') . "Collegati: {$updated}\n";
echo "Senza match: {$skipped}\n";
