#!/usr/bin/env php
<?php
/**
 * Diagnostica salvataggio Contratto (Quote).
 *
 *   php tools/diagnose-quote-save.php <quoteId> [statoFinanziamento]
 *   php tools/diagnose-quote-save.php 6a462adfd3eedc239 "Approvato"
 */
declare(strict_types=1);

$crmRoot = getenv('CRM_ROOT') ?: (getenv('HOME') . '/public_html/crm/mec-group');

if (!is_dir($crmRoot)) {
    $crmRoot = dirname(__DIR__);
}

require_once $crmRoot . '/bootstrap.php';

use Espo\Core\Application;
use Espo\ORM\EntityManager;

$quoteId = $argv[1] ?? null;
$newStato = $argv[2] ?? 'Approvato';

if (!$quoteId) {
    fwrite(STDERR, "Uso: php tools/diagnose-quote-save.php <quoteId> [statoFinanziamento]\n");
    exit(1);
}

$app = new Application();
$app->setupSystemUser();

/** @var EntityManager $em */
$em = $app->getContainer()->get('entityManager');

$quote = $em->getEntityById('Quote', $quoteId);

if (!$quote) {
    fwrite(STDERR, "Quote non trovato: {$quoteId}\n");
    exit(1);
}

echo "Quote: {$quote->get('name')}\n";
echo "hookVersion: " . ($quote->get('hookVersion') ?? '(vuoto)') . "\n";
echo "statoFinanziamento attuale: " . ($quote->get('statoFinanziamento') ?? '(vuoto)') . "\n";
echo "statoContratto: " . ($quote->get('statoContratto') ?? '(vuoto)') . "\n";
echo "Provo save con statoFinanziamento = {$newStato}\n\n";

$quote->set('statoFinanziamento', $newStato);

try {
    $em->saveEntity($quote);
    echo "OK: salvataggio riuscito.\n";
} catch (\Throwable $e) {
    echo "ERRORE: " . $e->getMessage() . "\n\n";
    echo $e->getTraceAsString() . "\n";
    exit(2);
}
