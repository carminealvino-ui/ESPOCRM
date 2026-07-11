#!/usr/bin/env php
<?php
/**
 * Allinea dataAppuntamento da dateStart per appuntamenti con dataAppuntamento vuota.
 * Utile quando KPI e grafici contano date diverse (dataAppuntamento vs dateStart).
 *
 *   php tools/bonifica-data-appuntamento.php --dry-run
 *   php tools/bonifica-data-appuntamento.php
 */
declare(strict_types=1);

$root = getenv('CRM_ROOT') ?: getcwd();

if (!is_file($root . '/bootstrap.php')) {
    fwrite(STDERR, "Eseguire da root CRM (bootstrap.php).\n");
    exit(1);
}

require_once $root . '/bootstrap.php';

use Espo\Core\Application;
use Espo\ORM\EntityManager;

$argv = $GLOBALS['argv'] ?? [];
$dryRun = in_array('--dry-run', $argv, true);

$app = new Application();
$em = $app->getContainer()->getByClass(EntityManager::class);

$updated = 0;
$skipped = 0;

$collection = $em->getRDBRepository('Appuntamento')
    ->where([
        'OR' => [
            ['dataAppuntamento' => null],
            ['dataAppuntamento' => ''],
        ],
    ])
    ->find();

foreach ($collection as $entity) {
    $dateStart = $entity->get('dateStart');

    if (!is_string($dateStart) || strlen($dateStart) < 10) {
        $skipped++;
        continue;
    }

    $date = substr($dateStart, 0, 10);

    if (!$dryRun) {
        $entity->set('dataAppuntamento', $date);
        $em->saveEntity($entity, ['skipHooks' => true]);
    }

    echo ($dryRun ? 'DRY ' : 'OK ') . $entity->getId() . ' → ' . $date . "\n";
    $updated++;
}

echo "\nAggiornati: {$updated}, saltati (senza dateStart): {$skipped}\n";
