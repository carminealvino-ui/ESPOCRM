#!/usr/bin/env php
<?php
/**
 * Imposta accountId sugli appuntamenti esistenti (prospect.cliente / lead.createdAccount / parent Account).
 *
 *   php tools/backfill-appuntamento-account-link.php --dry-run
 *   php tools/backfill-appuntamento-account-link.php --account-id=ID_CLIENTE
 */
declare(strict_types=1);

$root = getenv('CRM_ROOT') ?: getcwd();

if (!is_file($root . '/bootstrap.php')) {
    fwrite(STDERR, "Eseguire da root CRM.\n");
    exit(1);
}

require_once $root . '/bootstrap.php';

use Espo\Core\Application;
use Espo\ORM\Entity;

$app = new Application();
$app->setupSystemUser();
$em = $app->getContainer()->get('entityManager');

$dryRun = in_array('--dry-run', $argv, true);
$accountFilter = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--account-id=')) {
        $accountFilter = substr($arg, 13);
    }
}

function resolveAccountId(Entity $entity, $em): ?string
{
    if ($entity->get('parentType') === 'Account' && $entity->get('parentId')) {
        return $entity->get('parentId');
    }

    if ($entity->get('prospectId')) {
        $prospect = $em->getEntityById('Prospect', $entity->get('prospectId'));
        if ($prospect && $prospect->get('clienteId')
            && $em->getEntityById('Account', $prospect->get('clienteId'))) {
            return $prospect->get('clienteId');
        }
    }

    if ($entity->get('parentType') === 'Prospect' && $entity->get('parentId')) {
        $prospect = $em->getEntityById('Prospect', $entity->get('parentId'));
        if ($prospect && $prospect->get('clienteId')
            && $em->getEntityById('Account', $prospect->get('clienteId'))) {
            return $prospect->get('clienteId');
        }
    }

    if ($entity->get('parentType') === 'Lead' && $entity->get('parentId')) {
        $lead = $em->getEntityById('Lead', $entity->get('parentId'));
        if ($lead && $lead->get('createdAccountId')
            && $em->getEntityById('Account', $lead->get('createdAccountId'))) {
            return $lead->get('createdAccountId');
        }
    }

    return null;
}

$where = [];
if ($accountFilter) {
    $where['accountId'] = $accountFilter;
}

$repo = $em->getRDBRepository('Appuntamento');
$query = $repo->where($where)->order('createdAt', 'DESC')->limit(0, 500);

$updated = 0;
$skipped = 0;

foreach ($query->find() as $app) {
    if ($app->get('accountId')) {
        $skipped++;
        continue;
    }

    $accountId = resolveAccountId($app, $em);
    if (!$accountId) {
        $skipped++;
        continue;
    }

    if ($accountFilter && $accountId !== $accountFilter) {
        continue;
    }

    $account = $em->getEntityById('Account', $accountId);
    if (!$account) {
        continue;
    }

    if (!$dryRun) {
        $app->set([
            'accountId' => $accountId,
            'accountName' => $account->get('name'),
        ]);
        $em->saveEntity($app, ['silent' => true, 'skipHooks' => true]);
    }

    $updated++;
    fwrite(STDOUT, ($dryRun ? '[dry-run] ' : '') . $app->getId() . ' → ' . $account->get('name') . "\n");
}

fwrite(STDOUT, "Aggiornati: {$updated}, saltati: {$skipped}\n");
