<?php
/**
 * Imposta taxCode su listini (PriceBook) cercando TaxCode per codice (IVA10, IVA22).
 *
 *   php tools/set-pricebook-tax-code.php --tax-code=IVA10 --price-book-name='ARIEL'
 *   php tools/set-pricebook-tax-code.php --tax-code=IVA10 --all-missing
 */

declare(strict_types=1);

$options = getopt('', [
    'crm-root::',
    'tax-code:',
    'price-book-id::',
    'price-book-name::',
    'all-missing',
    'dry-run',
]);

if (empty($options['tax-code'])) {
    fwrite(STDERR, "Usage: php set-pricebook-tax-code.php --tax-code=IVA10 [--price-book-name=ARIEL|--all-missing]\n");
    exit(1);
}

$crmRoot = rtrim($options['crm-root'] ?? getcwd(), '/');
$configInternal = $crmRoot . '/data/config-internal.php';

if (!is_file($configInternal)) {
    fwrite(STDERR, "config-internal.php non trovato in {$crmRoot}/data/\n");
    exit(1);
}

chdir($crmRoot);
require_once $crmRoot . '/bootstrap.php';

$app = new \Espo\Core\Application();
$app->setupSystemUser();

$entityManager = $app->getContainer()->get('entityManager');
$dryRun = array_key_exists('dry-run', $options);
$taxCodeValue = strtoupper(trim((string) $options['tax-code']));

$taxCode = $entityManager
    ->getRDBRepository('TaxCode')
    ->where([
        'code' => $taxCodeValue,
        'status' => 'Active',
    ])
    ->findOne();

if (!$taxCode) {
    fwrite(STDERR, "TaxCode non trovato con codice: {$taxCodeValue}\n");
    exit(1);
}

$where = [];

if (!empty($options['price-book-id'])) {
    $where['id'] = $options['price-book-id'];
}

$collection = $entityManager->getRDBRepository('PriceBook')->where($where)->find();
$nameFilter = $options['price-book-name'] ?? null;
$allMissing = array_key_exists('all-missing', $options);

if (!$allMissing && !$nameFilter && empty($options['price-book-id'])) {
    fwrite(STDERR, "Specificare --price-book-name, --price-book-id o --all-missing\n");
    exit(1);
}

$updated = 0;

foreach ($collection as $priceBook) {
    if ($nameFilter && stripos((string) $priceBook->get('name'), $nameFilter) === false) {
        continue;
    }

    if ($priceBook->get('taxCodeId')) {
        continue;
    }

    if (!$priceBook->hasAttribute('taxCodeId')) {
        fwrite(STDERR, "ERRORE: campo taxCode assente su PriceBook. Eseguire php command.php rebuild.\n");
        exit(1);
    }

    $label = (string) $priceBook->get('name');

    if ($dryRun) {
        fwrite(STDOUT, "[dry-run] {$label} → {$taxCodeValue}\n");
        $updated++;
        continue;
    }

    $priceBook->set('taxCodeId', $taxCode->getId());
    $priceBook->set('taxCodeName', $taxCode->get('code'));
    $entityManager->saveEntity($priceBook);

    fwrite(STDOUT, "OK {$label} → {$taxCodeValue}\n");
    $updated++;
}

fwrite(STDOUT, "\nListini aggiornati: {$updated}" . ($dryRun ? ' (dry-run)' : '') . "\n");
