#!/usr/bin/env php
<?php
/**
 * Ripristina Cliente (account), Contraente e Contatto installazione su un Contratto
 * leggendo l'Opportunità collegata (stessa logica base di CreateContratto).
 *
 *   cd ~/public_html/crm/mec-group
 *   php tools/ripristina-cliente-contratto-da-opportunita.php --id=ID_CONTRATTO
 *   php tools/ripristina-cliente-contratto-da-opportunita.php --name="SANTOSUOSSO"
 *   php tools/ripristina-cliente-contratto-da-opportunita.php --scan --dry-run
 */
declare(strict_types=1);

use Espo\Core\Application;
use Espo\Custom\Services\ReferenteContactService;
use Espo\ORM\Entity;

$root = getenv('CRM_ROOT') ?: getcwd();

if (!is_file($root . '/bootstrap.php')) {
    fwrite(STDERR, "Eseguire da root CRM (bootstrap.php).\n");
    exit(1);
}

require_once $root . '/bootstrap.php';

$app = new Application();
$app->setupSystemUser();
$em = $app->getContainer()->get('entityManager');

$id = null;
$name = null;
$scan = false;
$dryRun = false;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--id=')) {
        $id = substr($arg, 5);
    }
    if (str_starts_with($arg, '--name=')) {
        $name = substr($arg, 7);
    }
    if ($arg === '--scan') {
        $scan = true;
    }
    if ($arg === '--dry-run') {
        $dryRun = true;
    }
}

/**
 * @return array{accountId:?string,accountName:?string,billingContactId:?string,billingContactName:?string,shippingContactId:?string,shippingContactName:?string}
 */
function resolveCustomerFromOpportunity(Entity $opportunity, $em): array
{
    $accountId = $opportunity->get('accountId');
    $lead = null;
    $prospect = null;

    if ($opportunity->get('leadId')) {
        $lead = $em->getEntityById('Lead', $opportunity->get('leadId'));
        if (!$accountId && $lead && $lead->get('createdAccountId')) {
            $accountId = $lead->get('createdAccountId');
        }
    }

    if ($opportunity->get('prospectId')) {
        $prospect = $em->getEntityById('Prospect', $opportunity->get('prospectId'));
        if (!$accountId && $prospect && $prospect->get('clienteId')) {
            $accountId = $prospect->get('clienteId');
        }
    }

    if ($accountId && !$em->getEntityById('Account', $accountId)) {
        $prospectAsAccount = $em->getEntityById('Prospect', $accountId);
        if ($prospectAsAccount && $prospectAsAccount->get('clienteId')
            && $em->getEntityById('Account', $prospectAsAccount->get('clienteId'))) {
            $accountId = $prospectAsAccount->get('clienteId');
            if (!$prospect) {
                $prospect = $prospectAsAccount;
            }
        } else {
            $accountId = null;
        }
    }

    $accountName = null;
    if ($accountId) {
        $account = $em->getEntityById('Account', $accountId);
        $accountName = $account ? $account->get('name') : null;
    }

    $billingContactId = null;
    $billingContactName = null;
    $shippingContactId = null;
    $shippingContactName = null;

    if ($accountId) {
        $service = new ReferenteContactService($em);
        $referente = $service->ensureForAccount($accountId, [
            'lead' => $lead,
            'prospect' => $prospect,
            'assignedUserId' => $opportunity->get('assignedUserId'),
        ]);

        if ($referente) {
            $billingContactId = $referente['id'];
            $billingContactName = $referente['name'];
            $shippingContactId = $referente['id'];
            $shippingContactName = $referente['name'];
        }
    }

    if (!$billingContactName && $accountName) {
        $billingContactName = $accountName;
    }
    if (!$shippingContactName) {
        $shippingContactName = $billingContactName;
    }

    return compact(
        'accountId',
        'accountName',
        'billingContactId',
        'billingContactName',
        'shippingContactId',
        'shippingContactName'
    );
}

function repairQuote(Entity $quote, $em, bool $dryRun): array
{
    $oppId = $quote->get('opportunityId');
    if (!$oppId) {
        return ['ok' => false, 'error' => 'Nessuna opportunità collegata'];
    }

    $opportunity = $em->getEntityById('Opportunity', $oppId);
    if (!$opportunity) {
        return ['ok' => false, 'error' => 'Opportunità non trovata'];
    }

    $data = resolveCustomerFromOpportunity($opportunity, $em);

    if (!$data['accountId'] && !$data['billingContactId']) {
        return ['ok' => false, 'error' => 'Impossibile risolvere cliente/referente da opportunità'];
    }

    $before = [
        'accountId' => $quote->get('accountId'),
        'billingContactId' => $quote->get('billingContactId'),
        'shippingContactId' => $quote->get('shippingContactId'),
    ];

    if (!$dryRun) {
        $quote->set($data);
        $em->saveEntity($quote, ['silent' => true]);
    }

    return [
        'ok' => true,
        'id' => $quote->getId(),
        'name' => $quote->get('name'),
        'before' => $before,
        'after' => $data,
        'dryRun' => $dryRun,
    ];
}

$repo = $em->getRDBRepository('Quote');
$quotes = [];

if ($id) {
    $q = $em->getEntityById('Quote', $id);
    if ($q) {
        $quotes[] = $q;
    }
} elseif ($name) {
    $quotes = $repo
        ->where(['name*' => '%' . $name . '%'])
        ->order('modifiedAt', 'DESC')
        ->limit(0, 5)
        ->find();
} elseif ($scan) {
    $quotes = $repo
        ->where([
            'opportunityId!=' => null,
            'OR' => [
                ['accountId' => null],
                ['billingContactId' => null],
            ],
        ])
        ->order('modifiedAt', 'DESC')
        ->limit(0, 50)
        ->find();
} else {
    fwrite(STDERR, "Usare --id=, --name= o --scan (opz. --dry-run)\n");
    exit(1);
}

if ($quotes === []) {
    fwrite(STDERR, "Nessun contratto trovato.\n");
    exit(1);
}

$results = [];
foreach ($quotes as $quote) {
    $results[] = repairQuote($quote, $em, $dryRun);
}

fwrite(STDOUT, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
