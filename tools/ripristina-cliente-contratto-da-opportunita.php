<?php

/**
 * Ripristina Cliente + Contraente su Contratti (Quote) senza account collegato.
 *
 *   cd ~/public_html/crm/mec-group
 *   php tools/ripristina-cliente-contratto-da-opportunita.php --scan --dry-run
 *   php tools/ripristina-cliente-contratto-da-opportunita.php --scan --limit=20
 *   php tools/ripristina-cliente-contratto-da-opportunita.php --fix-names --dry-run
 *   php tools/ripristina-cliente-contratto-da-opportunita.php --fix-names
 *   php tools/ripristina-cliente-contratto-da-opportunita.php --id=ID_CONTRATTO
 *   php tools/ripristina-cliente-contratto-da-opportunita.php --name="SABATINI"
 *   php tools/ripristina-cliente-contratto-da-opportunita.php --account-id=ID_ACCOUNT
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\ContrattoClienteFromOpportunitaResolver;
use Espo\ORM\Entity;

$dryRun = in_array('--dry-run', $argv ?? [], true);
$scan = in_array('--scan', $argv ?? [], true);
$fixNamesOnly = in_array('--fix-names', $argv ?? [], true);
$limit = null;
$id = null;
$name = null;
$accountId = null;

foreach ($argv ?? [] as $arg) {
    if ($arg === '--dry-run' || $arg === '--scan' || $arg === '--fix-names') {
        continue;
    }

    if (str_starts_with((string) $arg, '--limit=')) {
        $limit = max(1, (int) substr((string) $arg, 8));
        continue;
    }

    if (str_starts_with((string) $arg, '--id=')) {
        $id = trim(substr((string) $arg, 5));
        continue;
    }

    if (str_starts_with((string) $arg, '--name=')) {
        $name = trim(substr((string) $arg, 7));
        continue;
    }

    if (str_starts_with((string) $arg, '--account-id=')) {
        $accountId = trim(substr((string) $arg, 13));
        continue;
    }
}

$app = new Application();
$app->setupSystemUser();
$em = $app->getContainer()->get('entityManager');
$resolver = new ContrattoClienteFromOpportunitaResolver($em);

$quotes = [];

if ($id) {
    $quote = $em->getEntityById('Quote', $id);

    if ($quote) {
        $quotes[] = $quote;
    }
} elseif ($accountId) {
    $quotes = iterator_to_array(
        $em->getRDBRepository('Quote')
            ->where(['accountId' => $accountId])
            ->order('modifiedAt', 'DESC')
            ->limit(0, 10)
            ->find()
    );
} elseif ($name) {
    $quotes = $em->getRDBRepository('Quote')
        ->where(['name*' => '%' . $name . '%'])
        ->order('modifiedAt', 'DESC')
        ->limit(0, 10)
        ->find();
} elseif ($scan || $fixNamesOnly) {
    $query = $em->getRDBRepository('Quote');

    if ($fixNamesOnly) {
        $query = $query->where([
            'OR' => [
                ['accountId!=' => null, 'accountId!=' => ''],
                ['billingContactId!=' => null, 'billingContactId!=' => ''],
            ],
        ]);
    } else {
        $query = $query->where([
            'opportunityId!=' => null,
            'OR' => [
                ['accountId' => null],
                ['accountId' => ''],
                ['accountName' => null],
                ['accountName' => ''],
                ['billingContactId' => null],
                ['billingContactId' => ''],
                ['billingContactName' => null],
                ['billingContactName' => ''],
            ],
        ]);
    }

    $query = $query->order('modifiedAt', 'DESC');

    if ($limit !== null) {
        $query->limit(0, $limit);
    }

    $quotes = iterator_to_array($query->find());

    if ($fixNamesOnly) {
        $quotes = array_values(array_filter(
            $quotes,
            static fn (Entity $quote): bool => $resolver->quoteNeedsClienteRepair($quote)
        ));

        if ($limit !== null) {
            $quotes = array_slice($quotes, 0, $limit);
        }
    }
} else {
    fwrite(STDERR, "Usare --scan, --fix-names, --id=, --name= o --account-id=\n");
    exit(1);
}

if ($quotes === []) {
    if ($accountId) {
        $result = $resolver->repairEmptyAccountById($accountId, !$dryRun);
        $prefix = $result ? ($dryRun ? 'DRY OK' : 'OK') : 'SKIP';
        echo $prefix
            . ' | account '
            . $accountId
            . ' | '
            . ($result['message'] ?? 'Account non trovato o nome già presente')
            . ($result ? ' → ' . ($result['patch']['accountName'] ?? '') : '')
            . PHP_EOL;
        exit($result ? 0 : 1);
    }

    fwrite(STDERR, "Nessun contratto trovato.\n");
    exit(1);
}

$scanned = 0;
$updated = 0;
$skipped = 0;

foreach ($quotes as $quote) {
    $scanned++;
    $result = repairQuote($quote, $em, $resolver, $dryRun, $fixNamesOnly);

    $prefix = $result['ok'] ? ($dryRun ? 'DRY OK' : 'OK') : 'SKIP';

    echo $prefix
        . ' | '
        . $quote->getId()
        . ' | '
        . ($quote->get('name') ?: '∅')
        . ' | '
        . ($result['message'] ?? '');

    if ($result['ok'] && isset($result['cliente'])) {
        echo ' → ' . $result['cliente'];
    }

    echo PHP_EOL;

    $result['ok'] ? $updated++ : $skipped++;
}

echo PHP_EOL
    . "Scansionati={$scanned} aggiornati={$updated} skip={$skipped}"
    . ($dryRun ? ' (dry-run)' : '')
    . PHP_EOL;

/**
 * @return array{ok: bool, message: string, cliente?: string}
 */
function repairQuote(
    Entity $quote,
    $em,
    ContrattoClienteFromOpportunitaResolver $resolver,
    bool $dryRun,
    bool $fixNamesOnly = false
): array {
    $nameFix = repairExistingLinks($quote, $em, $resolver, $dryRun);

    if ($nameFix) {
        return $nameFix;
    }

    if ($fixNamesOnly) {
        return ['ok' => false, 'message' => 'Nomi già ok o link mancante'];
    }

    $oppId = $quote->get('opportunityId');

    if (!$oppId) {
        return ['ok' => false, 'message' => 'Nessuna opportunità collegata'];
    }

    $opportunity = $em->getEntityById('Opportunity', $oppId);

    if (!$opportunity) {
        return ['ok' => false, 'message' => 'Opportunità non trovata'];
    }

    $data = $resolver->resolveForQuote($quote, $opportunity, !$dryRun);

    if (!$data || !$data['accountId']) {
        return ['ok' => false, 'message' => 'Impossibile risolvere cliente (Lead/Prospect/nome contratto)'];
    }

    $patch = [
        'accountId' => $data['accountId'],
        'accountName' => $data['accountName'],
        'billingContactId' => $data['billingContactId'],
        'billingContactName' => $data['billingContactName'],
        'shippingContactId' => $data['shippingContactId'],
        'shippingContactName' => $data['shippingContactName'],
    ];

    if (!$dryRun) {
        $quote->set($patch);
        $em->saveEntity($quote, ['silent' => true, 'skipHooks' => true]);
        $resolver->syncOpportunity($opportunity, $data);
    }

    $flags = [];

    if ($data['createdAccount']) {
        $flags[] = 'nuovo Account';
    }

    if ($data['createdContact']) {
        $flags[] = 'nuovo Contact';
    }

    $message = $flags !== [] ? implode(', ', $flags) : 'collegato';

    return [
        'ok' => true,
        'message' => $message,
        'cliente' => ($data['accountName'] ?: '') . ' / ' . ($data['billingContactName'] ?: ''),
    ];
}

/**
 * @return array{ok: bool, message: string, cliente?: string}|null
 */
function repairExistingLinks(
    Entity $quote,
    $em,
    ContrattoClienteFromOpportunitaResolver $resolver,
    bool $dryRun
): ?array {
    $result = $resolver->repairBrokenClienteLink($quote, !$dryRun);

    if (!$result) {
        return null;
    }

    if (!$dryRun) {
        $quote->set($result['patch']);
        $em->saveEntity($quote, ['silent' => true, 'skipHooks' => true]);
    }

    $patch = $result['patch'];

    return [
        'ok' => true,
        'message' => $result['message'],
        'cliente' => ($patch['accountName'] ?? $quote->get('accountName') ?: '')
            . ' / '
            . ($patch['billingContactName'] ?? $quote->get('billingContactName') ?: ''),
    ];
}
