<?php
/**
 * Installa campi dual IVA su ProductPrice (colonne DB + metadata).
 *
 *   php tools/install-productprice-dual-iva-fields.php
 */

declare(strict_types=1);

$options = getopt('', ['crm-root::', 'dry-run']);

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
$dataManager = $container->get('dataManager');
$metadata = $container->get('metadata');
$entityManager = $container->get('entityManager');

$entityDefsPath = $crmRoot . '/custom/Espo/Custom/Resources/metadata/entityDefs/ProductPrice.json';
$requiredFields = [
    'prezzoListinoIvaInclusa',
    'prezzoListinoIvaEsclusa',
    'prezzoCodice',
    'prezzoCodiceIvaInclusa',
    'aliquotaIva',
];

$sample = $entityManager->getNewEntity('ProductPrice');
$missing = [];

foreach ($requiredFields as $field) {
    if (!$sample->hasAttribute($field)) {
        $missing[] = $field;
    }
}

fwrite(STDOUT, "Campi ProductPrice mancanti: " . (count($missing) ? implode(', ', $missing) : 'nessuno') . "\n");

if ($missing === [] && is_file($entityDefsPath)) {
    fwrite(STDOUT, "OK: campi dual IVA già registrati.\n");
    exit(0);
}

if (array_key_exists('dry-run', $options)) {
    fwrite(STDOUT, "[dry-run] Eseguirebbe rebuild da {$entityDefsPath}\n");
    exit(0);
}

if (!is_file($entityDefsPath)) {
    fwrite(STDERR, "File mancante: {$entityDefsPath}\n");
    fwrite(STDERR, "Eseguire prima deploy-productprice-dual-iva-listino.sh\n");
    exit(1);
}

fwrite(STDOUT, "Rebuild schema ProductPrice...\n");
$dataManager->rebuild();

foreach (['data/cache', 'data/tmp'] as $dir) {
    $path = $crmRoot . '/' . $dir;
    if (!is_dir($path)) {
        continue;
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($it as $file) {
        $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
    }
}

$metadata = $container->get('metadata');
$entityManager = $container->get('entityManager');
$sample = $entityManager->getNewEntity('ProductPrice');
$stillMissing = [];

foreach ($requiredFields as $field) {
    if (!$sample->hasAttribute($field)) {
        $stillMissing[] = $field;
    }
}

if ($stillMissing !== []) {
    fwrite(STDERR, "ERRORE: dopo rebuild mancano: " . implode(', ', $stillMissing) . "\n");
    exit(1);
}

fwrite(STDOUT, "OK: campi dual IVA ProductPrice installati.\n");
