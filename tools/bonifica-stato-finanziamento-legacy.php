#!/usr/bin/env php
<?php
/**
 * Normalizza statoFinanziamento legacy su Contratti e Opportunità.
 *
 * Uso:
 *   php tools/bonifica-stato-finanziamento-legacy.php --dry-run
 *   php tools/bonifica-stato-finanziamento-legacy.php --apply
 *   php tools/bonifica-stato-finanziamento-legacy.php --sql --quote-id=6a462adfd3eedc239
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
$sql = in_array('--sql', $argv, true);
$quoteId = null;

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--quote-id=')) {
        $quoteId = substr($arg, strlen('--quote-id='));
    }
}

if (!$dryRun && !$apply && !$sql) {
    fwrite(STDERR, "Specificare --dry-run, --apply oppure --sql\n");
    exit(1);
}

echo "=== Bonifica statoFinanziamento legacy ===\n";
if ($sql) {
    echo "Modalità: SQL diretto su tabella quote\n";
}

/** @var array<string, string> */
const LEGACY_MAP = [
    'In valutazione' => 'In lavorazione',
    'In attesa di OTP' => 'In lavorazione',
    'In attesa documentazione' => 'In Attesa Documentazione',
];

$app = new Application();
$app->setupSystemUser();

/** @var EntityManager $em */
$em = $app->getContainer()->get('entityManager');

/** @var Metadata $metadata */
$metadata = $app->getContainer()->get('metadata');

$entities = ['Quote', 'Opportunity'];
$updated = 0;
$scanned = 0;

foreach ($entities as $entityType) {
    $enumOptions = $metadata->get([
        'entityDefs',
        $entityType,
        'fields',
        'statoFinanziamento',
        'options',
    ]) ?? [];

    $repo = $em->getRDBRepository($entityType);

    if ($quoteId !== null && $entityType === 'Quote') {
        $collection = $repo->where(['id' => $quoteId])->find();
    } else {
        $collection = $repo->find();
    }

    foreach ($collection as $entity) {
        $scanned++;
        $current = trim((string) ($entity->get('statoFinanziamento') ?? ''));

        if ($current === '') {
            continue;
        }

        $target = LEGACY_MAP[$current] ?? null;

        if ($target === null && $enumOptions !== [] && !in_array($current, $enumOptions, true)) {
            $target = 'In lavorazione';
        }

        if ($target === null || $target === $current) {
            continue;
        }

        echo sprintf(
            "[%s] %s (%s): \"%s\" → \"%s\"\n",
            $entityType,
            (string) $entity->get('name'),
            (string) $entity->getId(),
            $current,
            $target
        );

        if ($apply) {
            $entity->set('statoFinanziamento', $target);
            $em->saveEntity($entity, [
                'skipHooks' => true,
                'silent' => true,
                'skipFormula' => true,
            ]);
        }

        if ($sql && $entityType === 'Quote') {
            $pdo = $em->getPDO();
            $stmt = $pdo->prepare(
                'UPDATE `quote` SET `stato_finanziamento` = :target WHERE `id` = :id AND `deleted` = 0'
            );
            $stmt->execute([
                'target' => $target,
                'id' => $entity->getId(),
            ]);
            echo "  → SQL UPDATE eseguito\n";
        }

        $updated++;
    }
}

$mode = $sql ? 'SQL' : ($apply ? 'APPLY' : 'DRY-RUN');
echo "\n{$mode}: {$updated} record aggiornati su {$scanned} scansionati.\n";

if ($dryRun && $updated > 0) {
    echo "Eseguire con --apply o --sql per applicare.\n";
}
