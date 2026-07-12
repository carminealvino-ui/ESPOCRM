#!/usr/bin/env php
<?php
/**
 * Verifica e corregge status legacy su Contratti (Quote).
 *
 *   php tools/bonifica-quote-status-legacy.php --dry-run
 *   php tools/bonifica-quote-status-legacy.php --dry-run --quote-id=6a36664f86ef51ce7
 *   php tools/bonifica-quote-status-legacy.php --apply --quote-id=6a36664f86ef51ce7
 */
declare(strict_types=1);

$crmRoot = getenv('CRM_ROOT') ?: (getenv('HOME') . '/public_html/crm/mec-group');

if (!is_dir($crmRoot)) {
    $crmRoot = dirname(__DIR__);
}

require_once $crmRoot . '/bootstrap.php';

use Espo\Core\Application;
use Espo\Core\Utils\Metadata;
use Espo\ORM\EntityManager;

$dryRun = in_array('--dry-run', $argv, true);
$apply = in_array('--apply', $argv, true);
$quoteId = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--quote-id=')) {
        $quoteId = substr($arg, strlen('--quote-id='));
    }
}

if (!$dryRun && !$apply) {
    fwrite(STDERR, "Specificare --dry-run oppure --apply\n");
    exit(1);
}

$app = new Application();
$app->setupSystemUser();

/** @var EntityManager $em */
$em = $app->getContainer()->get('entityManager');

/** @var Metadata $metadata */
$metadata = $app->getContainer()->get('metadata');

$enumOptions = $metadata->get(['entityDefs', 'Quote', 'fields', 'status', 'options']) ?? [];

echo "=== Bonifica status Contratto ===\n";
echo "Enum status in cache: " . implode(', ', $enumOptions) . "\n\n";

$repo = $em->getRDBRepository('Quote');
$collection = $quoteId !== null
    ? $repo->where(['id' => $quoteId])->find()
    : $repo->find();

$updated = 0;
$scanned = 0;

foreach ($collection as $quote) {
    $scanned++;
    $current = trim((string) ($quote->get('status') ?? ''));

    if ($current === '') {
        continue;
    }

    if ($enumOptions !== [] && in_array($current, $enumOptions, true)) {
        continue;
    }

    $target = resolveTargetStatus($quote, $current);

    echo sprintf(
        "[Quote] %s (%s): status \"%s\" → \"%s\"%s\n",
        (string) $quote->get('name'),
        (string) $quote->getId(),
        $current,
        $target,
        $enumOptions !== [] && !in_array($target, $enumOptions, true) ? ' (ATTENZIONE: target non in enum!)' : ''
    );

    if ($apply) {
        $quote->set('status', $target);
        $em->saveEntity($quote, [
            'skipHooks' => true,
            'silent' => true,
            'skipFormula' => true,
        ]);
        $updated++;
    }
}

$mode = $apply ? 'APPLY' : 'DRY-RUN';
echo "\n{$mode}: {$updated} aggiornati su {$scanned} scansionati.\n";

if ($enumOptions !== [] && !in_array('Invalido', $enumOptions, true)) {
    echo "\nATTENZIONE: enum non contiene 'Invalido' → eseguire deploy-fix-quote-stato-finanziamento.sh\n";
}

function resolveTargetStatus(object $quote, string $current): string
{
    $statoFin = trim((string) ($quote->get('statoFinanziamento') ?? ''));
    $statoContratto = trim((string) ($quote->get('statoContratto') ?? ''));

    if (in_array($statoFin, ['Respinto', 'Annullato'], true)) {
        return 'Finanziamento Rifiutato';
    }

    if (in_array($statoContratto, ['Annullato', 'Recesso'], true)) {
        return 'Invalido';
    }

    return match ($current) {
        'Bozza' => 'Bozza',
        'Appuntamento fissato' => 'Appuntamento fissato',
        'Invalido' => 'Invalido',
        'Draft' => 'Draft',
        'Presented' => 'Presented',
        'Approved' => 'Approved',
        'Canceled' => 'Canceled',
        default => 'In lavorazione',
    };
}
