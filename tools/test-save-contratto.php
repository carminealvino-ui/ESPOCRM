<?php

/**
 * Test salvataggio contratto (Quote) da CLI.
 * Uso: php tools/test-save-contratto.php [quoteId]
 */

$quoteId = $argv[1] ?? '6a462adfd3eedc239';

require dirname(__DIR__) . '/bootstrap.php';

$app = new Espo\Core\Application();
$app->setupSystemUser();

$em = $app->getContainer()->get('entityManager');
$quote = $em->getEntityById('Quote', $quoteId);

if (!$quote) {
    echo "Contratto non trovato: {$quoteId}\n";
    echo "Apri il contratto nel browser e copia l'ID dall'URL (dopo Quote/).\n";
    exit(1);
}

echo 'Prima del save:' . PHP_EOL;
echo '  stato=' . ($quote->get('status') ?? '') . PHP_EOL;
echo '  codiceContratto=' . ($quote->get('codiceContratto') ?? '') . PHP_EOL;
echo '  numeroContratto=' . ($quote->get('numeroContratto') ?? '') . PHP_EOL;
echo '  finanziamento=' . ($quote->get('finanziamento') ? 'si' : 'no') . PHP_EOL;

$quote->set([
    'numeroContratto' => '50318656',
    'codiceContratto' => '50318656',
    'finanziamento' => true,
    'statoFinanziamento' => 'In lavorazione',
]);

try {
    $em->saveEntity($quote);

    echo PHP_EOL . 'SALVATAGGIO OK' . PHP_EOL;
    echo '  stato=' . ($quote->get('status') ?? '') . PHP_EOL;
    echo '  codiceContratto=' . ($quote->get('codiceContratto') ?? '') . PHP_EOL;
    echo '  numeroContratto=' . ($quote->get('numeroContratto') ?? '') . PHP_EOL;
    echo '  totaleProvvigioni=' . ($quote->get('totaleProvvigioni') ?? '0') . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    echo PHP_EOL . 'ERRORE: ' . $e->getMessage() . PHP_EOL;
    echo $e->getFile() . ':' . $e->getLine() . PHP_EOL;
    exit(1);
}
