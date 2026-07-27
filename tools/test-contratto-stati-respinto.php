<?php
/**
 * Verifica che Respinto non venga sovrascritto da Annullato.
 *
 *   php tools/test-contratto-stati-respinto.php
 */

declare(strict_types=1);

$crmRoot = dirname(__DIR__);
chdir($crmRoot);
require_once $crmRoot . '/bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\ContrattoStatiRules;

$app = new Application();
$app->setupSystemUser();
$em = $app->getContainer()->get('entityManager');
$rules = $app->getContainer()->get('injectableFactory')->create(ContrattoStatiRules::class);

$fail = 0;

$quote = $em->getNewEntity('Quote');
$quote->set([
    'status' => 'Invalido',
    'statoContratto' => 'Annullato',
    'finanziamento' => true,
    'statoFinanziamento' => 'Respinto',
]);
$rules->apply($quote);

$got = (string) $quote->get('statoFinanziamento');
if ($got !== 'Respinto') {
    echo "FAIL: Annullato+Respinto → atteso Respinto, ottenuto {$got}\n";
    $fail++;
} else {
    echo "OK: Annullato+Respinto resta Respinto\n";
}

if ((string) $quote->get('status') !== 'Invalido') {
    echo 'FAIL: status atteso Invalido, ottenuto ' . $quote->get('status') . "\n";
    $fail++;
} else {
    echo "OK: status Invalido\n";
}

$quote2 = $em->getNewEntity('Quote');
$quote2->set([
    'status' => 'In Gestione',
    'statoContratto' => 'In lavorazione',
    'finanziamento' => true,
    'statoFinanziamento' => 'Respinto',
]);
$rules->apply($quote2);

if ((string) $quote2->get('statoContratto') !== 'Annullato') {
    echo 'FAIL: Respinto dovrebbe portare statoContratto Annullato, ottenuto '
        . $quote2->get('statoContratto') . "\n";
    $fail++;
} else {
    echo "OK: Respinto ⇒ statoContratto Annullato\n";
}

if ((string) $quote2->get('statoFinanziamento') !== 'Respinto') {
    echo 'FAIL: Respinto sovrascritto in ' . $quote2->get('statoFinanziamento') . "\n";
    $fail++;
} else {
    echo "OK: Respinto non sovrascritto dopo sync Annullato\n";
}

$quote3 = $em->getNewEntity('Quote');
$quote3->set([
    'status' => 'Invalido',
    'statoContratto' => 'Recesso',
    'finanziamento' => true,
    'statoFinanziamento' => 'Respinto',
]);
$rules->apply($quote3);

if ((string) $quote3->get('statoFinanziamento') !== 'Annullato') {
    echo 'FAIL: Recesso deve forzare Annullato, ottenuto '
        . $quote3->get('statoFinanziamento') . "\n";
    $fail++;
} else {
    echo "OK: Recesso ⇒ fin. Annullato (priorità su Respinto)\n";
}

if ($fail > 0) {
    echo "\nFALLITI: {$fail}\n";
    exit(1);
}

echo "\nTutti i test OK\n";
