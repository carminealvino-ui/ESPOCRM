<?php

/**
 * Diagnostica salvataggio contratto — mostra dove si blocca o fallisce.
 * Uso: php tools/diagnose-save-contratto.php [quoteId]
 */

$quoteId = $argv[1] ?? '6a462adfd3eedc239';

require dirname(__DIR__) . '/bootstrap.php';

$fatal = null;
register_shutdown_function(static function () use (&$fatal): void {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $fatal = $error;
        fwrite(STDERR, PHP_EOL . 'FATAL: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line'] . PHP_EOL);
    }
});

function step(string $message): void
{
    echo $message . PHP_EOL;
    if (function_exists('flush')) {
        @flush();
    }
}

step('=== Diagnostica salvataggio contratto ===');
step('ID: ' . $quoteId);

$app = new Espo\Core\Application();
$app->setupSystemUser();
$em = $app->getContainer()->get('entityManager');

$quote = $em->getEntityById('Quote', $quoteId);
if (!$quote) {
    step('ERRORE: contratto non trovato');
    exit(1);
}

step('Stato attuale: status=' . ($quote->get('status') ?? '') . ' finanziamento=' . ($quote->get('finanziamento') ? 'si' : 'no'));

$pdo = $em->getPDO();
$columns = ['numero_contratto', 'codice_contratto', 'finanziamento', 'totale_provvigioni'];
foreach ($columns as $column) {
    $stmt = $pdo->prepare('SHOW COLUMNS FROM quote LIKE :col');
    $stmt->execute(['col' => $column]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    step('Colonna ' . $column . ': ' . ($row ? 'OK' : 'MANCANTE'));
}

$quote->set([
    'numeroContratto' => '50318656',
    'codiceContratto' => '50318656',
    'finanziamento' => true,
    'statoFinanziamento' => 'In lavorazione',
]);

step('--- Test 1: save con skipHooks (senza hook) ---');
try {
    $em->saveEntity($quote, ['skipHooks' => true]);
    step('OK skipHooks: status=' . ($quote->get('status') ?? ''));
} catch (Throwable $e) {
    step('ERRORE skipHooks: ' . $e->getMessage());
    step($e->getFile() . ':' . $e->getLine());
    exit(1);
}

$quote = $em->getEntityById('Quote', $quoteId);
$quote->set([
    'numeroContratto' => '50318656',
    'codiceContratto' => '50318656',
    'finanziamento' => true,
    'statoFinanziamento' => 'In lavorazione',
    'status' => 'Draft',
]);

step('--- Test 2: save completo (con hook e formula) ---');
try {
    $em->saveEntity($quote);
    step('OK completo: status=' . ($quote->get('status') ?? ''));
    step('codiceContratto=' . ($quote->get('codiceContratto') ?? ''));
    step('totaleProvvigioni=' . ($quote->get('totaleProvvigioni') ?? '0'));
    exit(0);
} catch (Throwable $e) {
    step('ERRORE completo: ' . $e->getMessage());
    step($e->getFile() . ':' . $e->getLine());
    exit(1);
}
