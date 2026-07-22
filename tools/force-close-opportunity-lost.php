<?php

/**
 * Chiusura forzata Opportunity + Lead Perso.
 *
 *   php tools/force-close-opportunity-lost.php 6a572257e78a8c64a
 *   php tools/force-close-opportunity-lost.php 6a572257e78a8c64a --dry-run
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use Espo\Core\Application;

$argvList = $argv ?? [];
$dryRun = in_array('--dry-run', $argvList, true);
$opportunityId = null;

foreach ($argvList as $i => $arg) {
    if ($i === 0 || str_starts_with((string) $arg, '--')) {
        continue;
    }

    $opportunityId = trim((string) $arg);
    break;
}

if ($opportunityId === null || $opportunityId === '') {
    fwrite(STDERR, "Uso: php tools/force-close-opportunity-lost.php <opportunityId> [--dry-run]\n");
    exit(1);
}

$app = new Application();
$app->setupSystemUser();
$em = $app->getContainer()->get('entityManager');

$opportunity = $em->getEntityById('Opportunity', $opportunityId);

if (!$opportunity) {
    echo "Opportunity non trovata: {$opportunityId}\n";
    exit(2);
}

echo "OPP id={$opportunity->getId()}\n";
echo "  name={$opportunity->get('name')}\n";
echo "  stage={$opportunity->get('stage')} probability={$opportunity->get('probability')}\n";
echo "  leadId={$opportunity->get('leadId')} prospectId={$opportunity->get('prospectId')}\n";
echo "  appuntamentoId={$opportunity->get('appuntamentoId')} telefono={$opportunity->get('telefono')}\n";

$leadId = $opportunity->get('leadId');
$lead = $leadId ? $em->getEntityById('Lead', $leadId) : null;

if ($lead) {
    echo "LEAD id={$lead->getId()} status={$lead->get('status')} statoGestione={$lead->get('statoGestione')} name={$lead->get('name')}\n";
} else {
    echo "LEAD non collegato o non trovato\n";
}

if ($dryRun) {
    echo "DRY-RUN: nessuna modifica\n";
    exit(0);
}

$closeDate = (new DateTimeImmutable('now', new DateTimeZone('Europe/Rome')))->format('Y-m-d');

$update = $em->getQueryBuilder()
    ->update()
    ->in('Opportunity')
    ->set([
        'stage' => 'Closed Lost',
        'probability' => 0,
        'closeDate' => $closeDate,
    ])
    ->where(['id' => $opportunityId])
    ->build();

$em->getQueryExecutor()->execute($update);

echo "UPDATED Opportunity stage=Closed Lost closeDate={$closeDate}\n";

if ($lead) {
    $lead->set([
        'status' => 'Dead',
        'statoGestione' => 'Trattativa Chiusa',
    ]);
    $em->saveEntity($lead, ['silent' => true, 'skipAcl' => true]);
    echo "UPDATED Lead status=Dead statoGestione=Trattativa Chiusa\n";
}

$fresh = $em->getEntityById('Opportunity', $opportunityId);
echo "VERIFY stage={$fresh->get('stage')} probability={$fresh->get('probability')} closeDate={$fresh->get('closeDate')}\n";

if ($leadId) {
    $freshLead = $em->getEntityById('Lead', $leadId);
    if ($freshLead) {
        echo "VERIFY lead status={$freshLead->get('status')} statoGestione={$freshLead->get('statoGestione')}\n";
    }
}
