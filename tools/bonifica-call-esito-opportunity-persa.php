<?php

/**
 * Bonifica retroattiva: Call già esitate "Non interessato"
 * → opportunità Closed Lost + lead Perso.
 * L'Appuntamento Pending resta invariato (storicizzazione).
 *
 *   cd ~/public_html/crm/mec-group
 *   php tools/bonifica-call-esito-opportunity-persa.php --dry-run
 *   php tools/bonifica-call-esito-opportunity-persa.php
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\CallEsitoOpportunitySync;
use Espo\Custom\Services\LeadProspectSync;

$dryRun = in_array('--dry-run', $argv ?? [], true);

$app = new Application();
$app->setupSystemUser();

$entityManager = $app->getContainer()->get('entityManager');
$leadProspectSync = new LeadProspectSync($entityManager);
$sync = new CallEsitoOpportunitySync($entityManager, $leadProspectSync);

$collection = $entityManager
    ->getRDBRepository('Call')
    ->where([
        'status' => ['Held', 'Not Held'],
        'esito' => CallEsitoOpportunitySync::ESITO_NON_INTERESSATO,
    ])
    ->find();

$callsProcessed = 0;
$opportunitiesClosed = 0;
$leadsUpdated = 0;
$skipped = 0;

$terminalStages = [
    'Closed Won',
    'Closed Lost',
    'Chiusa persa',
    'Chiuso Negativamente',
];

foreach ($collection as $call) {
    if ($dryRun) {
        $opportunityIds = $sync->resolveOpportunityIds($call);
        $leadId = $sync->resolveLeadId($call);
        $wouldClose = 0;

        foreach ($opportunityIds as $opportunityId) {
            $opportunity = $entityManager->getEntityById('Opportunity', $opportunityId);

            if (!$opportunity) {
                continue;
            }

            $stage = (string) $opportunity->get('stage');

            if (!in_array($stage, $terminalStages, true)) {
                $wouldClose++;
            }
        }

        $wouldUpdateLead = 0;

        if ($leadId) {
            $lead = $entityManager->getEntityById('Lead', $leadId);

            if (
                $lead
                && (
                    $lead->get('status') !== 'Dead'
                    || $lead->get('statoGestione') !== 'Trattativa Chiusa'
                )
            ) {
                $wouldUpdateLead = 1;
            }
        }

        if ($wouldClose === 0 && $wouldUpdateLead === 0) {
            $skipped++;
            continue;
        }

        echo 'DRY ' . $call->getId()
            . ' opp=' . $wouldClose
            . ' lead=' . $wouldUpdateLead
            . PHP_EOL;

        $callsProcessed++;
        $opportunitiesClosed += $wouldClose;
        $leadsUpdated += $wouldUpdateLead;

        continue;
    }

    $result = $sync->syncFromCall($call);

    if ($result['opportunitiesClosed'] === 0 && $result['leadsUpdated'] === 0) {
        $skipped++;

        continue;
    }

    echo 'OK ' . $call->getId()
        . ' opp=' . $result['opportunitiesClosed']
        . ' lead=' . $result['leadsUpdated']
        . PHP_EOL;

    $callsProcessed++;
    $opportunitiesClosed += $result['opportunitiesClosed'];
    $leadsUpdated += $result['leadsUpdated'];
}

echo PHP_EOL;
echo ($dryRun ? 'DRY-RUN ' : '') . 'Call elaborate: ' . $callsProcessed . PHP_EOL;
echo 'Opportunità chiuse: ' . $opportunitiesClosed . PHP_EOL;
echo 'Lead aggiornati: ' . $leadsUpdated . PHP_EOL;
echo 'Già allineati: ' . $skipped . PHP_EOL;
