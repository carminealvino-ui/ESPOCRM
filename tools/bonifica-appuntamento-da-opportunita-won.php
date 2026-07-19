<?php

/**
 * Allinea Appuntamenti non sincronizzati con Opportunità vinte/installate.
 *
 *   php tools/bonifica-appuntamento-da-opportunita-won.php --dry-run
 *   php tools/bonifica-appuntamento-da-opportunita-won.php
 *   php tools/bonifica-appuntamento-da-opportunita-won.php --dry-run lommi
 *   php tools/bonifica-appuntamento-da-opportunita-won.php lommi
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\OpportunityAppuntamentoOutcomeSync;

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
$sync = new OpportunityAppuntamentoOutcomeSync($em);

$where = [
    [
        'OR' => [
            ['stage' => OpportunityAppuntamentoOutcomeSync::STAGE_WON],
            ['statoContratto' => 'Installato'],
        ],
    ],
];

if ($filter) {
    $where[] = [
        'OR' => [
            ['id' => $filter],
            ['name*' => $filter],
            ['accountName*' => $filter],
            ['prospectName*' => $filter],
        ],
    ];
}

$collection = $em->getRDBRepository('Opportunity')
    ->where($where)
    ->find();

$scanned = 0;
$updated = 0;
$linked = 0;
$skipped = 0;

foreach ($collection as $opportunity) {
    $scanned++;

    $result = $sync->syncFromOpportunity($opportunity, $dryRun);

    if (!$result['updated'] && !$result['linked']) {
        $skipped++;
        continue;
    }

    if ($result['linked'] && !$result['updated']) {
        echo ($dryRun ? 'DRY ' : 'OK  ')
            . 'LINK ' . $opportunity->getId()
            . ' | ' . $opportunity->get('name')
            . ' | app=' . $result['appuntamentoId']
            . PHP_EOL;
        $linked++;
        continue;
    }

    $before = $result['changes']['_before'] ?? [];
    $esitoFinal = $result['changes']['esito'] ?? ($before['esito'] ?? 'Venduto Cartaceo');

    echo ($dryRun ? 'DRY ' : 'OK  ')
        . $opportunity->getId()
        . ' | ' . $opportunity->get('name')
        . ' | app=' . $result['appuntamentoId']
        . ($result['linked'] ? ' (+link)' : '')
        . ' | '
        . ($before['status'] ?? '?') . '/' . ($before['sottostato'] ?? '?') . '/'
        . (($before['esito'] ?? '') !== '' ? $before['esito'] : '(vuoto)')
        . ' → Held/Chiuso Positivamente/' . $esitoFinal
        . PHP_EOL;

    $updated++;
    if ($result['linked']) {
        $linked++;
    }
}

echo PHP_EOL
    . "Scansionate={$scanned} aggiornate={$updated} linkate={$linked} già_ok={$skipped}"
    . ($dryRun ? ' (dry-run)' : '')
    . PHP_EOL;
