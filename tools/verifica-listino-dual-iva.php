<?php
/**
 * Verifica deploy listino dual IVA in produzione.
 *
 *   php tools/verifica-listino-dual-iva.php
 */

declare(strict_types=1);

$crmRoot = rtrim(getcwd(), '/');
$configInternal = $crmRoot . '/data/config-internal.php';

if (!is_file($configInternal)) {
    fwrite(STDERR, "Eseguire dalla root CRM (dove esiste data/config-internal.php)\n");
    exit(1);
}

require_once $crmRoot . '/bootstrap.php';

$app = new \Espo\Core\Application();
$app->setupSystemUser();

$entityManager = $app->getContainer()->get('entityManager');
$defs = $app->getContainer()->get('metadata');
$errors = 0;

function check(bool $ok, string $label): void
{
    global $errors;

    if ($ok) {
        fwrite(STDOUT, "OK  {$label}\n");
        return;
    }

    fwrite(STDOUT, "FAIL {$label}\n");
    $errors++;
}

$layoutPath = $crmRoot . '/custom/Espo/Custom/Resources/layouts/PriceBook/detail.json';
$layoutRaw = is_file($layoutPath) ? file_get_contents($layoutPath) : '';
check(is_file($layoutPath), 'layout PriceBook/detail.json presente');
check(str_contains($layoutRaw, '"taxCode"'), 'layout PriceBook contiene taxCode');

check(
    $defs->get(['entityDefs', 'PriceBook', 'fields', 'taxCode', 'type']) === 'link',
    'entityDefs PriceBook.taxCode type=link'
);

check(
    ($defs->get(['entityDefs', 'PriceBook', 'links', 'taxCode', 'entity']) ?? '') === 'TaxCode',
    'entityDefs PriceBook.taxCode → TaxCode'
);

check(
    is_file($crmRoot . '/custom/Espo/Custom/Hooks/ProductPrice/DualIvaPricing.php'),
    'hook DualIvaPricing.php presente'
);

check(
    is_file($crmRoot . '/custom/Espo/Custom/Services/IvaDualPriceSync.php'),
    'service IvaDualPriceSync.php presente'
);

$sample = $entityManager
    ->getRDBRepository('ProductPrice')
    ->where(['price>' => 0])
    ->limit(0, 1)
    ->findOne();

if ($sample) {
    $ivi = (float) ($sample->get('prezzoListinoIvaInclusa') ?? 0);
    $net = (float) ($sample->get('prezzoListinoIvaEsclusa') ?? 0);
    check($ivi > 0 || $net > 0, 'almeno un ProductPrice con dual IVA valorizzato');
} else {
    fwrite(STDOUT, "WARN nessun ProductPrice con price>0 da verificare\n");
}

$book = $entityManager->getRDBRepository('PriceBook')->limit(0, 1)->findOne();

if ($book && $book->hasAttribute('taxCodeId')) {
    check((bool) $book->get('taxCodeId'), 'almeno un PriceBook con taxCode impostato');
} else {
    check(false, 'campo taxCodeId su PriceBook (rebuild?)');
}

fwrite(STDOUT, $errors === 0 ? "\nVerifica completata: tutto OK\n" : "\nVerifica completata: {$errors} problemi\n");
exit($errors === 0 ? 0 : 1);
