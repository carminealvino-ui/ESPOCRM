<?php

/**
 * Diagnosi + fix mirato: Call "Non interessato" ↔ Opportunity/Lead.
 *
 *   cd ~/public_html/crm/mec-group
 *   php tools/diagnose-call-esito-opportunity.php SEDDA
 *   php tools/diagnose-call-esito-opportunity.php SEDDA --fix
 *   php tools/diagnose-call-esito-opportunity.php 3714109804 --fix
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\CallEsitoOpportunitySync;
use Espo\Custom\Services\LeadProspectSync;

$argvList = $argv ?? [];
$fix = in_array('--fix', $argvList, true);
$query = null;

foreach ($argvList as $i => $arg) {
    if ($i === 0 || str_starts_with((string) $arg, '--')) {
        continue;
    }

    $query = trim((string) $arg);
    break;
}

if ($query === null || $query === '') {
    fwrite(STDERR, "Uso: php tools/diagnose-call-esito-opportunity.php <nome|telefono|callId> [--fix]\n");
    exit(1);
}

$app = new Application();
$app->setupSystemUser();

$entityManager = $app->getContainer()->get('entityManager');
$sync = new CallEsitoOpportunitySync($entityManager, new LeadProspectSync($entityManager));

echo "=== DIAGNOSE query={$query} fix=" . ($fix ? 'yes' : 'no') . " ===\n";

$call = $entityManager->getEntityById('Call', $query);

if (!$call) {
    $call = $entityManager
        ->getRDBRepository('Call')
        ->where([
            'OR' => [
                ['name*' => $query],
                ['telefono*' => $query],
                ['parentName*' => $query],
            ],
            'esito' => CallEsitoOpportunitySync::ESITO_NON_INTERESSATO,
        ])
        ->order('modifiedAt', 'DESC')
        ->findOne();
}

if (!$call) {
    $call = $entityManager
        ->getRDBRepository('Call')
        ->where([
            'OR' => [
                ['name*' => $query],
                ['telefono*' => $query],
                ['parentName*' => $query],
            ],
        ])
        ->order('modifiedAt', 'DESC')
        ->findOne();
}

if (!$call) {
    echo "Call non trovata per: {$query}\n";
} else {
    echo "Call id={$call->getId()}\n";
    echo "  name={$call->get('name')}\n";
    echo "  status={$call->get('status')} esito={$call->get('esito')}\n";
    echo "  parent={$call->get('parentType')}/{$call->get('parentId')} {$call->get('parentName')}\n";
    echo "  prospectId={$call->get('prospectId')} telefono={$call->get('telefono')}\n";
    echo "  nota=" . substr((string) $call->get('nota'), 0, 160) . "\n";
    echo "  appuntamentoFromNota=" . $sync->extractAppuntamentoId((string) $call->get('nota')) . "\n";
    echo "  resolvedLeadId=" . $sync->resolveLeadId($call) . "\n";
    echo "  resolvedProspectId=" . $sync->resolveProspectId($call) . "\n";
}

echo "\n--- Opportunity per nome/telefono (indipendente dalla Call) ---\n";

$opportunities = $entityManager
    ->getRDBRepository('Opportunity')
    ->where([
        'OR' => [
            ['name*' => $query],
            ['telefono*' => $query],
            ['prospectName*' => $query],
        ],
    ])
    ->order('createdAt', 'DESC')
    ->limit(0, 20)
    ->find();

$oppByQuery = [];

foreach ($opportunities as $opportunity) {
    $oppByQuery[] = $opportunity->getId();
    echo "OPP {$opportunity->getId()}"
        . " stage={$opportunity->get('stage')}"
        . " leadId={$opportunity->get('leadId')}"
        . " prospectId={$opportunity->get('prospectId')}"
        . " appuntamentoId={$opportunity->get('appuntamentoId')}"
        . " telefono={$opportunity->get('telefono')}"
        . " name={$opportunity->get('name')}\n";
}

if ($oppByQuery === []) {
    echo "(nessuna Opportunity trovata con name/telefono {$query})\n";
}

$opportunityIds = [];

if ($call) {
    $opportunityIds = $sync->resolveOpportunityIds($call);
    echo "\n--- Opportunity risolte dalla Call ---\n";
    echo "opportunityIds=" . implode(',', $opportunityIds) . "\n";

    foreach ($opportunityIds as $opportunityId) {
        $opportunity = $entityManager->getEntityById('Opportunity', $opportunityId);

        if (!$opportunity) {
            echo "  opp {$opportunityId} MISSING\n";
            continue;
        }

        echo "  opp {$opportunityId} stage={$opportunity->get('stage')}"
            . " leadId={$opportunity->get('leadId')}"
            . " prospectId={$opportunity->get('prospectId')}"
            . " appuntamentoId={$opportunity->get('appuntamentoId')}"
            . " name={$opportunity->get('name')}\n";
    }
}

if (!$fix) {
    echo "\nDry-run. Per applicare: aggiungi --fix\n";
    exit(0);
}

echo "\n=== APPLY FIX ===\n";

if ($call) {
    $result = $sync->syncFromCall($call);
    echo "fromCall opportunitiesClosed={$result['opportunitiesClosed']}"
        . " leadsUpdated={$result['leadsUpdated']}"
        . " closed=" . implode(',', $result['opportunityIds'])
        . "\n";
}

// Fallback diretto: chiudi Opportunity aperte trovate per nome/telefono.
$terminal = ['Closed Won', 'Closed Lost', 'Chiusa persa', 'Chiuso Negativamente'];
$closeDate = (new DateTimeImmutable('now', new DateTimeZone('Europe/Rome')))->format('Y-m-d');
$forced = 0;

foreach ($oppByQuery as $opportunityId) {
    $opportunity = $entityManager->getEntityById('Opportunity', $opportunityId);

    if (!$opportunity) {
        continue;
    }

    if (in_array((string) $opportunity->get('stage'), $terminal, true)) {
        continue;
    }

    $update = $entityManager
        ->getQueryBuilder()
        ->update()
        ->in('Opportunity')
        ->set([
            'stage' => 'Closed Lost',
            'probability' => 0,
            'closeDate' => $closeDate,
        ])
        ->where(['id' => $opportunityId])
        ->build();

    $entityManager->getQueryExecutor()->execute($update);
    $forced++;

    echo "FORCED Closed Lost opp={$opportunityId}\n";

    $leadId = $opportunity->get('leadId');

    if ($leadId) {
        $lead = $entityManager->getEntityById('Lead', $leadId);

        if ($lead && ($lead->get('status') !== 'Dead' || $lead->get('statoGestione') !== 'Trattativa Chiusa')) {
            $lead->set([
                'status' => 'Dead',
                'statoGestione' => 'Trattativa Chiusa',
            ]);
            $entityManager->saveEntity($lead, ['silent' => true, 'skipAcl' => true]);
            echo "FORCED Lead Perso lead={$leadId}\n";
        }
    }
}

echo "forcedOpportunities={$forced}\n";

// Ricarica e verifica.
echo "\n=== VERIFY ===\n";

foreach ($oppByQuery as $opportunityId) {
    $opportunity = $entityManager->getEntityById('Opportunity', $opportunityId);

    if (!$opportunity) {
        continue;
    }

    echo "OPP {$opportunityId} stage={$opportunity->get('stage')} probability={$opportunity->get('probability')}\n";
}
