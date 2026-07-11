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
use Espo\Core\Utils\Metadata;
use Espo\ORM\EntityManager;

$quoteId = $argv[1] ?? null;
$newStato = $argv[2] ?? 'Approvato';

if (!$quoteId) {
    fwrite(STDERR, "Uso: php tools/diagnose-quote-save.php <quoteId> [statoFinanziamento]\n");
    exit(1);
}

$out = static function (string $message): void {
    echo $message . "\n";

    if (function_exists('ob_flush')) {
        @ob_flush();
    }

    flush();
};

register_shutdown_function(static function () use ($out): void {
    $error = error_get_last();

    if ($error === null) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

    if (!in_array($error['type'], $fatalTypes, true)) {
        return;
    }

    $out('ERRORE FATALE: ' . $error['message'] . ' in ' . $error['file'] . ':' . $error['line']);
});

$app = new Application();
$app->setupSystemUser();

/** @var EntityManager $em */
$em = $app->getContainer()->get('entityManager');

/** @var Metadata $metadata */
$metadata = $app->getContainer()->get('metadata');

$quote = $em->getEntityById('Quote', $quoteId);

if (!$quote) {
    fwrite(STDERR, "Quote non trovato: {$quoteId}\n");
    exit(1);
}

$enumOptions = $metadata->get(['entityDefs', 'Quote', 'fields', 'statoFinanziamento', 'options']) ?? [];

$out('Quote: ' . (string) $quote->get('name'));
$out('hookVersion: ' . (string) ($quote->get('hookVersion') ?? '(vuoto)'));
$out('statoFinanziamento attuale: ' . (string) ($quote->get('statoFinanziamento') ?? '(vuoto)'));
$out('statoContratto: ' . (string) ($quote->get('statoContratto') ?? '(vuoto)'));

$currentStato = trim((string) ($quote->get('statoFinanziamento') ?? ''));

if ($currentStato !== '' && $enumOptions !== [] && !in_array($currentStato, $enumOptions, true)) {
    $out('ATTENZIONE: valore attuale NON presente in enum entityDefs → deploy enum legacy richiesto');
}

if ($enumOptions !== [] && !in_array($newStato, $enumOptions, true)) {
    $out('ERRORE: stato richiesto "' . $newStato . '" non è in enum entityDefs');
    $out('Opzioni: ' . implode(', ', array_filter($enumOptions, static fn ($v) => $v !== '')));
    exit(3);
}

$out('Provo save con statoFinanziamento = ' . $newStato);

$quote->set('statoFinanziamento', $newStato);

try {
    $startedAt = microtime(true);
    $em->saveEntity($quote);
    $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);

    $saved = $em->getEntityById('Quote', $quoteId);
    $savedStato = $saved ? (string) ($saved->get('statoFinanziamento') ?? '(vuoto)') : '(non ricaricato)';

    $out('OK: salvataggio riuscito (' . $elapsedMs . ' ms)');
    $out('statoFinanziamento dopo save: ' . $savedStato);
} catch (\Throwable $e) {
    $out('ERRORE: ' . $e->getMessage());
    $out('');
    $out($e->getTraceAsString());
    exit(2);
}
