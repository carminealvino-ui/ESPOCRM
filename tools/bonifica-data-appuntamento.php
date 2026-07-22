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
    $date = null;

    $dateStart = $entity->get('dateStart');

    if (is_string($dateStart) && strlen($dateStart) >= 10) {
        $date = substr($dateStart, 0, 10);
    }

    if (!$date) {
        $date = resolveDateFromLinkedOpportunity($em, $entity);
    }

    if (!$date) {
        $skipped++;
        continue;
    }

    if (!$dryRun) {
        $entity->set('dataAppuntamento', $date);
        $em->saveEntity($entity, ['skipHooks' => true]);
    }

    echo ($dryRun ? 'DRY ' : 'OK ') . $entity->getId() . ' → ' . $date . "\n";
    $updated++;
}

echo "\nAggiornati: {$updated}, saltati (senza data): {$skipped}\n";

function resolveDateFromLinkedOpportunity(EntityManager $em, \Espo\ORM\Entity $appuntamento): ?string
{
    $opportunity = $em->getRDBRepository('Opportunity')
        ->where(['appuntamentoId' => $appuntamento->getId()])
        ->findOne();

    if (!$opportunity) {
        return null;
    }

    $dataOpportunit = $opportunity->get('dataOpportunit');

    if (is_string($dataOpportunit) && preg_match('/^\d{4}-\d{2}-\d{2}/', $dataOpportunit)) {
        return substr($dataOpportunit, 0, 10);
    }

    $name = (string) $opportunity->get('name');

    if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $name, $matches)) {
        return $matches[1];
    }

    return null;
}
