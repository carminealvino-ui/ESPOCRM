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
    exit(2);
}

echo "Call id={$call->getId()}\n";
echo "  name={$call->get('name')}\n";
echo "  status={$call->get('status')} esito={$call->get('esito')}\n";
echo "  parent={$call->get('parentType')}/{$call->get('parentId')} {$call->get('parentName')}\n";
echo "  prospectId={$call->get('prospectId')} telefono={$call->get('telefono')}\n";
echo "  nota=" . substr((string) $call->get('nota'), 0, 120) . "\n";

$appuntamentoId = $sync->extractAppuntamentoId((string) $call->get('nota'));
echo "  appuntamentoFromNota={$appuntamentoId}\n";

$leadId = $sync->resolveLeadId($call);
echo "  resolvedLeadId={$leadId}\n";

if ($leadId) {
    $lead = $entityManager->getEntityById('Lead', $leadId);
    if ($lead) {
        echo "  lead status={$lead->get('status')} statoGestione={$lead->get('statoGestione')}\n";
    }
}

$opportunityIds = $sync->resolveOpportunityIds($call);
echo "  opportunityIds=" . implode(',', $opportunityIds) . "\n";

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

if (!$fix) {
    echo "\nDry-run. Per applicare: aggiungi --fix\n";
    exit(0);
}

$result = $sync->syncFromCall($call);
echo "\nFIX opportunitiesClosed={$result['opportunitiesClosed']}"
    . " leadsUpdated={$result['leadsUpdated']}"
    . " closed=" . implode(',', $result['opportunityIds'])
    . "\n";
