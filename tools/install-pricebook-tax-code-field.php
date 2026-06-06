<?php
/**
 * Installa / ripara campo link taxCode su PriceBook → TaxCode (Imposta - Codici).
 *
 *   cd ~/public_html/crm/mec-group
 *   php tools/install-pricebook-tax-code-field.php
 *   php tools/install-pricebook-tax-code-field.php --force-json
 */

declare(strict_types=1);

$options = getopt('', ['crm-root::', 'force-json', 'dry-run']);

$crmRoot = rtrim($options['crm-root'] ?? getcwd(), '/');
$configInternal = $crmRoot . '/data/config-internal.php';

if (!is_file($configInternal)) {
    fwrite(STDERR, "Eseguire dalla root CRM.\n");
    exit(1);
}

chdir($crmRoot);
require_once $crmRoot . '/bootstrap.php';

$app = new \Espo\Core\Application();
$app->setupSystemUser();

$container = $app->getContainer();
$entityManager = $container->get('entityManager');
$metadata = $container->get('metadata');
$dataManager = $container->get('dataManager');
$pdo = $entityManager->getPDO();

$entityDefsPath = $crmRoot . '/custom/Espo/Custom/Resources/metadata/entityDefs/PriceBook.json';
$canonicalJson = <<<'JSON'
{
    "fields": {
        "name": {
            "options": []
        },
        "isTaxInclusive": {
            "readOnlyAfterCreate": false
        },
        "taxCode": {
            "type": "link",
            "entity": "TaxCode",
            "required": false,
            "audited": true,
            "isCustom": true,
            "autocompleteOnEmpty": true,
            "tooltip": true
        }
    },
    "links": {
        "taxCode": {
            "type": "belongsTo",
            "entity": "TaxCode",
            "foreignName": "code",
            "isCustom": true
        }
    }
}
JSON;

$dryRun = array_key_exists('dry-run', $options);

function columnExists(\PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);

    return (int) $stmt->fetchColumn() > 0;
}

$currentType = $metadata->get(['entityDefs', 'PriceBook', 'fields', 'taxCode', 'type']);
$hasTaxCodeId = $entityManager->getNewEntity('PriceBook')->hasAttribute('taxCodeId');
$hasWrongColumn = columnExists($pdo, 'price_book', 'tax_code')
    && !columnExists($pdo, 'price_book', 'tax_code_id');

fwrite(STDOUT, "Stato attuale:\n");
fwrite(STDOUT, '  metadata type taxCode: ' . ($currentType ?? 'ASSENTE') . "\n");
fwrite(STDOUT, '  attribute taxCodeId: ' . ($hasTaxCodeId ? 'SI' : 'NO') . "\n");
fwrite(STDOUT, '  colonna tax_code (varchar errata): ' . ($hasWrongColumn ? 'SI' : 'NO') . "\n");

if ($hasWrongColumn) {
    fwrite(STDERR, "\nERRORE: esiste colonna tax_code (testo) invece di tax_code_id (link).\n");
    fwrite(STDERR, "Amministrazione → Entity Manager → PriceBook → elimina campo taxCode (tipo sbagliato).\n");
    fwrite(STDERR, "Poi riesegui: php tools/install-pricebook-tax-code-field.php\n");
    exit(1);
}

if ($currentType !== null && $currentType !== 'link') {
    fwrite(STDERR, "\nERRORE: taxCode esiste ma type={$currentType}, serve type=link.\n");
    fwrite(STDERR, "Amministrazione → Entity Manager → PriceBook → elimina campo taxCode.\n");
    fwrite(STDERR, "Poi riesegui questo script.\n");
    exit(1);
}

if (!$hasTaxCodeId || array_key_exists('force-json', $options) || !is_file($entityDefsPath)) {
    if ($dryRun) {
        fwrite(STDOUT, "\n[dry-run] Scriverebbe entityDefs + rebuild\n");
        exit(0);
    }

    mkdir(dirname($entityDefsPath), 0775, true);
    file_put_contents($entityDefsPath, $canonicalJson . "\n");
    fwrite(STDOUT, "\nScritto {$entityDefsPath}\n");

    fwrite(STDOUT, "Rebuild in corso...\n");
    $dataManager->rebuild();

    foreach (['data/cache', 'data/tmp'] as $dir) {
        $path = $crmRoot . '/' . $dir;
        if (is_dir($path)) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($it as $file) {
                $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
            }
        }
    }

    $metadata = $container->get('metadata');
    $entityManager = $container->get('entityManager');
}

$newType = $metadata->get(['entityDefs', 'PriceBook', 'fields', 'taxCode', 'type']);
$hasTaxCodeId = $entityManager->getNewEntity('PriceBook')->hasAttribute('taxCodeId');
$linkEntity = $metadata->get(['entityDefs', 'PriceBook', 'links', 'taxCode', 'entity']);

fwrite(STDOUT, "\nDopo rebuild:\n");
fwrite(STDOUT, '  metadata type taxCode: ' . ($newType ?? 'ASSENTE') . "\n");
fwrite(STDOUT, '  link entity: ' . ($linkEntity ?? 'ASSENTE') . "\n");
fwrite(STDOUT, '  attribute taxCodeId: ' . ($hasTaxCodeId ? 'SI' : 'NO') . "\n");

if ($newType !== 'link' || !$hasTaxCodeId) {
    fwrite(STDERR, "\nInstallazione fallita. Controlla log rebuild e permessi su custom/.\n");
    exit(1);
}

if (!columnExists($pdo, 'price_book', 'tax_code_name')) {
    fwrite(STDOUT, "Colonna tax_code_name assente → ALTER TABLE...\n");
    if (!$dryRun) {
        $pdo->exec('ALTER TABLE price_book ADD COLUMN tax_code_name VARCHAR(100) DEFAULT NULL AFTER tax_code_id');
    }
}

if (!$dryRun && columnExists($pdo, 'price_book', 'tax_code_name')) {
    $n = $pdo->exec(
        'UPDATE price_book pb
         INNER JOIN tax_code tc ON tc.id = pb.tax_code_id AND tc.deleted = 0
         SET pb.tax_code_name = tc.code
         WHERE pb.deleted = 0
           AND pb.tax_code_id IS NOT NULL
           AND (pb.tax_code_name IS NULL OR pb.tax_code_name = \'\')'
    );
    fwrite(STDOUT, "Backfill tax_code_name: {$n} listini\n");
}

fwrite(STDOUT, "\nOK: taxCode link → TaxCode (Imposta - Codici) installato.\n");
