<?php
/**
 * Cerca prodotto FALCON / prezzo codice.
 *   php tools/diagnose-prezzo-codice-product.php
 *   php tools/diagnose-prezzo-codice-product.php --q=FALCON
 */
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

$q = 'FALCON';
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--q=')) {
        $q = substr($arg, 4);
    }
}

$app = new Espo\Core\Application();
$app->setupSystemUser();
$em = $app->getContainer()->get('entityManager');

$collection = $em->getRDBRepository('Product')
    ->where(['name*' => $q])
    ->limit(0, 15)
    ->find();

echo "Query name*={$q}\n";
$count = 0;
foreach ($collection as $p) {
    $count++;
    echo "---\n";
    echo 'id=' . $p->getId() . "\n";
    echo 'name=' . $p->get('name') . "\n";
    echo 'prezzoCodice=' . var_export($p->get('prezzoCodice'), true) . "\n";
    echo 'prezzoCodiceIvaInclusa=' . var_export($p->get('prezzoCodiceIvaInclusa'), true) . "\n";
    echo 'listPrice=' . var_export($p->get('listPrice'), true) . "\n";
    echo 'prezzoListinoIvaInclusa=' . var_export($p->get('prezzoListinoIvaInclusa'), true) . "\n";
}
echo "Trovati: {$count}\n";
