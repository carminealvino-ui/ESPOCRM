<?php

/**
 * Bonifica retroattiva: Call già esitate "Non interessato" → opportunità persa
 * e appuntamento Pending → Non Interessato.
 *
 *   cd ~/public_html/crm/mec-group
 *   php tools/bonifica-call-esito-opportunity-persa.php
 *   php tools/bonifica-call-esito-opportunity-persa.php --dry-run
 */

require_once __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\CallEsitoOpportunitySync;

$dryRun = in_array('--dry-run', $argv ?? [], true);

$app = new Application();
$app->setupSystemUser();

$entityManager = $app->getContainer()->get('entityManager');
$sync = new CallEsitoOpportunitySync($entityManager);

$collection = $entityManager
    ->getRDBRepository('Call')
    ->where([
        'status' => ['Held', 'Not Held'],
        'esito' => CallEsitoOpportunitySync::ESITO_NON_INTERESSATO,
    ])
    ->find();

$callsProcessed = 0;
$opportunitiesClosed = 0;
$appuntamentiUpdated = 0;
$skipped = 0;

foreach ($collection as $call) {
    if ($dryRun) {
        $opportunityIds = $sync->resolveOpportunityIds($call);
        $appuntamentoId = $sync->extractAppuntamentoId((string) $call->get('nota'));
        $wouldClose = 0;

        foreach ($opportunityIds as $opportunityId) {
            $opportunity = $entityManager->getEntityById('Opportunity', $opportunityId);

            if (!$opportunity) {
                continue;
            }

            $stage = (string) $opportunity->get('stage');

            if (!in_array($stage, ['Closed Won', 'Closed Lost', 'Chiusa persa', 'Chiuso Negativamente'], true)) {
                $wouldClose++;
            }
        }

        $wouldUpdateApp = 0;

        if ($appuntamentoId) {
            $appuntamento = $entityManager->getEntityById('Appuntamento', $appuntamentoId);

            if ($appuntamento && $appuntamento->get('sottostato') === 'Pending') {
                $wouldUpdateApp = 1;
            }
        }

        if ($wouldClose === 0 && $wouldUpdateApp === 0) {
            $skipped++;
            continue;
        }

        echo 'DRY ' . $call->getId()
            . ' opp=' . $wouldClose
            . ' app=' . $wouldUpdateApp
            . PHP_EOL;

        $callsProcessed++;
        $opportunitiesClosed += $wouldClose;
        $appuntamentiUpdated += $wouldUpdateApp;

        continue;
    }

    $result = $sync->syncFromCall($call);

    if ($result['opportunitiesClosed'] === 0 && $result['appuntamentiUpdated'] === 0) {
        $skipped++;

        continue;
    }

    echo 'OK ' . $call->getId()
        . ' opp=' . $result['opportunitiesClosed']
        . ' app=' . $result['appuntamentiUpdated']
        . PHP_EOL;

    $callsProcessed++;
    $opportunitiesClosed += $result['opportunitiesClosed'];
    $appuntamentiUpdated += $result['appuntamentiUpdated'];
}

echo PHP_EOL;
echo ($dryRun ? 'DRY-RUN ' : '') . 'Call elaborate: ' . $callsProcessed . PHP_EOL;
echo 'Opportunità chiuse: ' . $opportunitiesClosed . PHP_EOL;
echo 'Appuntamenti aggiornati: ' . $appuntamentiUpdated . PHP_EOL;
echo 'Già allineati: ' . $skipped . PHP_EOL;
