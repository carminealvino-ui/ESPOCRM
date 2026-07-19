<?php
/**
 * Crea i due Contact (Lead + Prospect) sul Cliente se mancano.
 *
 * Uso:
 *   php tools/ensure-referenti-lead-prospect.php
 *   php tools/ensure-referenti-lead-prospect.php --account-name="NOTO FEDERICA"
 *   php tools/ensure-referenti-lead-prospect.php --quote-number=Contratto_00153
 */

require dirname(__DIR__) . '/bootstrap.php';

use Espo\Custom\Services\ReferenteContactService;

$app = new Espo\Core\Application();
$app->setupSystemUser();
$em = $app->getContainer()->get('entityManager');
$service = new ReferenteContactService($em);

$accountName = null;
$quoteNumber = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--account-name=')) {
        $accountName = substr($arg, strlen('--account-name='));
    }
    if (str_starts_with($arg, '--quote-number=')) {
        $quoteNumber = substr($arg, strlen('--quote-number='));
    }
}

$accounts = [];

if ($quoteNumber) {
    $quote = $em->getRDBRepository('Quote')
        ->where([
            'OR' => [
                ['number' => $quoteNumber],
                ['numberA' => $quoteNumber],
                ['name' => $quoteNumber],
            ],
        ])
        ->findOne();

    if (!$quote || !$quote->get('accountId')) {
        fwrite(STDERR, "Contratto non trovato o senza Cliente: {$quoteNumber}\n");
        exit(1);
    }

    $account = $em->getEntityById('Account', $quote->get('accountId'));
    if ($account) {
        $accounts[] = $account;
    }
} elseif ($accountName) {
    $found = $em->getRDBRepository('Account')
        ->where(['name' => $accountName])
        ->find();
    foreach ($found as $account) {
        $accounts[] = $account;
    }
} else {
    fwrite(STDERR, "Specifica --account-name=... oppure --quote-number=...\n");
    exit(1);
}

if (!$accounts) {
    fwrite(STDERR, "Nessun Cliente trovato.\n");
    exit(1);
}

foreach ($accounts as $account) {
    $accountId = $account->getId();
    $lead = $em->getRDBRepository('Lead')
        ->where(['createdAccountId' => $accountId])
        ->findOne();

    if (!$lead) {
        $lead = $em->getRDBRepository('Lead')
            ->where(['name' => $account->get('name')])
            ->findOne();
    }

    $prospect = null;
    if ($account->get('prospectId')) {
        $prospect = $em->getEntityById('Prospect', $account->get('prospectId'));
    }
    if (!$prospect && $lead) {
        $prospectId = $lead->get('prospectId');
        if ($prospectId) {
            $prospect = $em->getEntityById('Prospect', $prospectId);
        }
    }

    $opp = $em->getRDBRepository('Opportunity')
        ->where(['accountId' => $accountId])
        ->order('createdAt', 'DESC')
        ->findOne();

    if ($opp) {
        if (!$lead && $opp->get('leadId')) {
            $lead = $em->getEntityById('Lead', $opp->get('leadId'));
        }
        if (!$prospect && $opp->get('prospectId')) {
            $prospect = $em->getEntityById('Prospect', $opp->get('prospectId'));
        }
    }

    $pair = $service->ensureLeadAndProspectForAccount($accountId, [
        'lead' => $lead,
        'prospect' => $prospect,
        'assignedUserId' => $account->get('assignedUserId'),
    ]);

    echo "Cliente: {$account->get('name')} ({$accountId})\n";
    echo "  Lead contact:     "
        . ($pair['billingContactName'] ?? '-') . ' / '
        . ($pair['billingContactId'] ?? '-') . "\n";
    echo "  Prospect contact: "
        . ($pair['shippingContactName'] ?? '-') . ' / '
        . ($pair['shippingContactId'] ?? '-') . "\n";

    if ($opp && !empty($pair['leadContact']['id']) && !$opp->get('contactId')) {
        $opp->set([
            'contactId' => $pair['leadContact']['id'],
            'contactName' => $pair['leadContact']['name'],
        ]);
        $em->saveEntity($opp, ['silent' => true, 'skipHooks' => true]);
    }

    $quotes = [];
    if (isset($quote) && $quote) {
        $quotes[] = $quote;
    } else {
        foreach ($em->getRDBRepository('Quote')->where(['accountId' => $accountId])->find() as $q) {
            $quotes[] = $q;
        }
    }

    foreach ($quotes as $q) {
        $patch = [];
        if (!empty($pair['billingContactId'])) {
            $patch['billingContactId'] = $pair['billingContactId'];
            $patch['billingContactName'] = $pair['billingContactName'];
        }
        if (!empty($pair['shippingContactId'])) {
            $patch['shippingContactId'] = $pair['shippingContactId'];
            $patch['shippingContactName'] = $pair['shippingContactName'];
        }
        if (!$patch) {
            continue;
        }
        $q->set($patch);
        $em->saveEntity($q, ['silent' => true, 'skipHooks' => true]);
        echo "  Contratto aggiornato: {$q->get('name')}\n";
    }
}

echo "OK\n";
