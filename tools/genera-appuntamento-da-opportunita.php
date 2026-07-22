<?php

/**
 * Collega o genera Appuntamento per Opportunità senza appuntamento collegato.
 *
 *   php tools/genera-appuntamento-da-opportunita.php --dry-run
 *   php tools/genera-appuntamento-da-opportunita.php --dry-run --limit=20
 *   php tools/genera-appuntamento-da-opportunita.php --link-only --dry-run
 *   php tools/genera-appuntamento-da-opportunita.php --id=OPPORTUNITY_ID
 *   php tools/genera-appuntamento-da-opportunita.php monaco
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\OpportunityAppuntamentoGenerator;

$dryRun = in_array('--dry-run', $argv ?? [], true);
$linkOnly = in_array('--link-only', $argv ?? [], true);
$limit = null;
$filterId = null;
$filterText = null;

foreach ($argv ?? [] as $i => $arg) {
    if ($i === 0) {
        continue;
    }

    if ($arg === '--dry-run' || $arg === '--link-only') {
        continue;
    }

    if (str_starts_with((string) $arg, '--limit=')) {
        $limit = max(1, (int) substr((string) $arg, 8));
        continue;
    }

    if (str_starts_with((string) $arg, '--id=')) {
        $filterId = trim(substr((string) $arg, 5));
        continue;
    }

    if (!str_starts_with((string) $arg, '--')) {
        $filterText = trim((string) $arg);
    }
}

$app = new Application();
$app->setupSystemUser();
$em = $app->getContainer()->get('entityManager');
$generator = new OpportunityAppuntamentoGenerator($em);

$query = [
    'OR' => [
        ['appuntamentoId' => null],
        ['appuntamentoId' => ''],
    ],
];

if ($filterId) {
    unset($query['OR']);
    $query['id'] = $filterId;
}

$collection = $em
    ->getRDBRepository('Opportunity')
    ->where($query)
    ->order('createdAt', 'DESC')
    ->find();

$scanned = 0;
$linked = 0;
$created = 0;
$skipped = 0;
$processed = 0;

foreach ($collection as $opportunity) {
    if ($limit !== null && $processed >= $limit) {
        break;
    }

    if ($filterText) {
        $needle = mb_strtolower($filterText);
        $hay = mb_strtolower(implode(' ', [
            (string) $opportunity->getId(),
            (string) $opportunity->get('name'),
            (string) $opportunity->get('prospectName'),
            (string) $opportunity->get('leadName'),
        ]));

        if (!str_contains($hay, $needle)) {
            continue;
        }
    }

    $scanned++;

    $result = $generator->process($opportunity, $dryRun, $linkOnly);

    $prefix = match ($result['action']) {
        'linked' => 'LINK',
        'created' => 'NEW ',
        default => 'SKIP',
    };

    echo ($dryRun ? 'DRY ' : '')
        . $prefix
        . ' | '
        . $opportunity->getId()
        . ' | '
        . ($opportunity->get('name') ?: '∅')
        . ' | '
        . $result['reason'];

    if ($result['appuntamentoId']) {
        echo ' → ' . $result['appuntamentoId'];

        if ($result['appuntamentoName']) {
            echo ' (' . $result['appuntamentoName'] . ')';
        }
    }

    echo PHP_EOL;

    match ($result['action']) {
        'linked' => $linked++,
        'created' => $created++,
        default => $skipped++,
    };

    $processed++;
}

echo PHP_EOL
    . "Scansionati={$scanned} collegati={$linked} creati={$created} skip={$skipped}"
    . ($dryRun ? ' (dry-run)' : '')
    . ($linkOnly ? ' (link-only)' : '')
    . PHP_EOL;
