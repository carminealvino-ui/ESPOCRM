#!/usr/bin/env php
<?php
/**
 * Ri-salva le provvigioni per applicare collegamenti Cliente / Contratto / Opportunità / nome.
 *
 *   php tools/backfill-provvigione-campi-collegati.php --dry-run
 *   php tools/backfill-provvigione-campi-collegati.php --verbose
 *   php tools/backfill-provvigione-campi-collegati.php --quote-id=ID_CONTRATTO
 *   php tools/backfill-provvigione-campi-collegati.php --all
 */
declare(strict_types=1);

$root = getenv('CRM_ROOT') ?: getcwd();

if (!is_file($root . '/bootstrap.php')) {
    fwrite(STDERR, "Eseguire da root CRM (es. ~/public_html/crm/mec-group).\n");
    exit(1);
}

require_once $root . '/bootstrap.php';

use Espo\Core\Application;

$app = new Application();
$app->setupSystemUser();
$em = $app->getContainer()->get('entityManager');

$dryRun = in_array('--dry-run', $argv, true);
$verbose = in_array('--verbose', $argv, true);
$quoteFilter = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--quote-id=')) {
        $quoteFilter = substr($arg, 11);
    }
}

$all = in_array('--all', $argv, true);
$where = $all
    ? ['deleted' => false]
    : [
        'deleted' => false,
        [
            'OR' => [
                ['contrattoId!=' => null],
                ['opportunitaId!=' => null],
            ],
        ],
    ];

if ($quoteFilter) {
    $where['contrattoId'] = $quoteFilter;
}

$collection = $em->getRDBRepository('Provvigione')->where($where)->find();
$updated = 0;
$skipped = 0;

foreach ($collection as $provvigione) {
    $id = $provvigione->getId();
    $quoteId = $provvigione->get('contrattoId');
    $oppId = $provvigione->get('opportunitaId');

    if (!$quoteId && !$oppId) {
        $skipped++;
        continue;
    }

    if ($verbose || $dryRun) {
        fwrite(
            STDOUT,
            ($dryRun ? '[dry-run] ' : '')
            . "Provvigione {$id} → contratto " . ($quoteId ?: '-') . " / opportunita " . ($oppId ?: '-') . "\n"
        );
    }

    if ($dryRun) {
        $updated++;
        continue;
    }

    $em->saveEntity($provvigione, ['skipHooks' => false]);
    $updated++;
}

fwrite(STDOUT, "Provvigioni processate: {$updated}, saltate: {$skipped}\n");

exit(0);
