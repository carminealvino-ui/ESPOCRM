<?php

/**
 * Ripristina Cliente + Contraente su Contratti (Quote) senza account collegato.
 *
 *   cd ~/public_html/crm/mec-group
 *   php tools/ripristina-cliente-contratto-da-opportunita.php --scan --dry-run
 *   php tools/ripristina-cliente-contratto-da-opportunita.php --scan --limit=20
 *   php tools/ripristina-cliente-contratto-da-opportunita.php --scan --fix-names
 *   php tools/ripristina-cliente-contratto-da-opportunita.php --id=ID_CONTRATTO
 *   php tools/ripristina-cliente-contratto-da-opportunita.php --name="SABATINI"
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
                [
                    'AND' => [
                        ['accountId!=' => null],
                        ['accountId!=' => ''],
                        ['OR' => [
                            ['accountName' => null],
                            ['accountName' => ''],
                        ]],
                    ],
                ],
                [
                    'AND' => [
                        ['billingContactId!=' => null],
                        ['billingContactId!=' => ''],
                        ['OR' => [
                            ['billingContactName' => null],
                            ['billingContactName' => ''],
                        ]],
                    ],
                ],
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

    $quotes = $query->find();
} else {
    fwrite(STDERR, "Usare --scan, --fix-names, --id= o --name=\n");
    exit(1);
}

if ($quotes === []) {
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
    $nameFix = fixExistingLinkNames($quote, $em, $dryRun);

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
function fixExistingLinkNames(Entity $quote, $em, bool $dryRun): ?array
{
    $patch = [];

    $accountId = $quote->get('accountId');

    if ($accountId) {
        $account = $em->getEntityById('Account', $accountId);

        if ($account) {
            $accountName = trim((string) $account->get('name'));

            if ($accountName !== '' && needsNameRepair($quote->get('accountName'), $accountId)) {
                $patch['accountName'] = $accountName;
            }
        }
    }

    foreach ([
        ['billingContactId', 'billingContactName', 'Contact'],
        ['shippingContactId', 'shippingContactName', 'Contact'],
    ] as [$idField, $nameField, $entityType]) {
        $relatedId = $quote->get($idField);

        if (!$relatedId) {
            continue;
        }

        $related = $em->getEntityById($entityType, $relatedId);

        if (!$related) {
            continue;
        }

        $relatedName = trim((string) $related->get('name'));

        if ($relatedName !== '' && needsNameRepair($quote->get($nameField), $relatedId)) {
            $patch[$nameField] = $relatedName;
        }
    }

    if ($patch === []) {
        return null;
    }

    if (!$dryRun) {
        $quote->set($patch);
        $em->saveEntity($quote, ['silent' => true, 'skipHooks' => true]);
    }

    return [
        'ok' => true,
        'message' => 'nomi link aggiornati',
        'cliente' => ($patch['accountName'] ?? $quote->get('accountName') ?: $accountId) . ' / '
            . ($patch['billingContactName'] ?? $quote->get('billingContactName') ?: ''),
    ];
}

function needsNameRepair(mixed $currentName, string $id): bool
{
    $currentName = trim((string) $currentName);

    if ($currentName === '') {
        return true;
    }

    if ($currentName === $id) {
        return true;
    }

    return (bool) preg_match('/^[a-f0-9]{17,24}$/i', $currentName);
}
