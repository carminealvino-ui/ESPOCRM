<?php
/**
 * Ripristina nome provvigioni (codice contratto in testa) e link contratto/cliente.
 *
 *   php tools/backfill-provvigioni-nomi.php
 *   php tools/backfill-provvigioni-nomi.php --dry-run
 */

declare(strict_types=1);

$crmRoot = getenv('CRM_ROOT') ?: (getenv('HOME') . '/public_html/crm/mec-group');

if (!is_dir($crmRoot)) {
    $crmRoot = dirname(__DIR__);
}

chdir($crmRoot);
require_once $crmRoot . '/bootstrap.php';

use Espo\Core\Application;
use Espo\ORM\EntityManager;

$dryRun = in_array('--dry-run', $argv ?? [], true);

$app = new Application();
$app->setupSystemUser();

/** @var EntityManager $em */
$em = $app->getContainer()->get('entityManager');

$collection = $em->getRDBRepository('Provvigione')->find();

$updated = 0;
$skipped = 0;

foreach ($collection as $provvigione) {
    $name = trim((string) ($provvigione->get('name') ?? ''));
    $needsFix = $name === ''
        || str_starts_with($name, 'PROVV-')
        || !$provvigione->get('contrattoId');

    if (!$needsFix) {
        $skipped++;
        continue;
    }

    if ($dryRun) {
        echo '[DRY] ' . ($name ?: '(vuoto)') . ' id=' . $provvigione->getId() . "\n";
        $updated++;
        continue;
    }

    $em->saveEntity($provvigione);
    $fresh = $em->getEntityById('Provvigione', $provvigione->getId());
    echo '[OK] ' . ($fresh?->get('name') ?? $name) . "\n";
    $updated++;
}

echo "\nAggiornate: {$updated}, già ok: {$skipped}\n";
