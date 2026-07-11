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
use Espo\ORM\Entity;
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

$entityDefsFile = $crmRoot . '/custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json';
$fileEnumOptions = [];

if (is_readable($entityDefsFile)) {
    $raw = json_decode((string) file_get_contents($entityDefsFile), true);
    $fileEnumOptions = $raw['fields']['statoFinanziamento']['options'] ?? [];
}

$enumOptions = $metadata->get(['entityDefs', 'Quote', 'fields', 'statoFinanziamento', 'options']) ?? [];

$hookFile = $crmRoot . '/custom/Espo/Custom/Hooks/Quote/SyncContractPricing.php';
$hookSrc = is_readable($hookFile) ? (string) file_get_contents($hookFile) : '';

if ($hookSrc !== '' && !str_contains($hookSrc, 'shouldRunFullPricingSync')) {
    $out('ATTENZIONE: SyncContractPricing senza whitelist — eseguire deploy-fix-quote-stato-finanziamento.sh');
}

$quote = $em->getEntityById('Quote', $quoteId);

if (!$quote) {
    fwrite(STDERR, "Quote non trovato: {$quoteId}\n");
    exit(1);
}

$out('Quote: ' . (string) $quote->get('name'));
$out('hookVersion: ' . (string) ($quote->get('hookVersion') ?? '(vuoto)'));
$out('statoFinanziamento attuale: ' . (string) ($quote->get('statoFinanziamento') ?? '(vuoto)'));
$out('statoContratto: ' . (string) ($quote->get('statoContratto') ?? '(vuoto)'));
$out('finanziamento: ' . ((bool) $quote->get('finanziamento') ? 'true' : 'false'));

$currentStato = trim((string) ($quote->get('statoFinanziamento') ?? ''));

if ($fileEnumOptions !== []) {
    $out('enum su file: ' . implode(', ', array_filter($fileEnumOptions, static fn ($v) => $v !== '')));
}

if ($enumOptions !== []) {
    $out('enum in cache metadata: ' . implode(', ', array_filter($enumOptions, static fn ($v) => $v !== '')));
}

if ($fileEnumOptions !== $enumOptions) {
    $out('ATTENZIONE: enum file ≠ cache metadata → eseguire php clear_cache.php && php rebuild.php');
}

if ($currentStato !== '' && $enumOptions !== [] && !in_array($currentStato, $enumOptions, true)) {
    $out('ATTENZIONE: valore attuale NON presente in enum cache');
}

if ($enumOptions !== [] && !in_array($newStato, $enumOptions, true)) {
    $out('ERRORE: stato richiesto "' . $newStato . '" non è in enum entityDefs');
    exit(3);
}

$trySave = static function (
    EntityManager $em,
    Entity $entity,
    array $options,
    string $label
) use ($out): bool {
    $clone = $em->getEntityById('Quote', $entity->getId());

    if (!$clone) {
        $out("  {$label}: impossibile ricaricare entità");
        return false;
    }

    $clone->set('statoFinanziamento', $entity->get('statoFinanziamento'));

    try {
        $startedAt = microtime(true);
        $em->saveEntity($clone, $options);
        $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);
        $out("  {$label}: OK ({$elapsedMs} ms)");

        return true;
    } catch (\Throwable $e) {
        $out("  {$label}: ERRORE — " . $e->getMessage());
        return false;
    }
};

$out('');
$out('=== Test salvataggio a strati (statoFinanziamento = ' . $newStato . ') ===');

$quote->set('statoFinanziamento', $newStato);

$trySave($em, $quote, [
    'skipHooks' => true,
    'skipFormula' => true,
    'silent' => true,
], '1) skipHooks + skipFormula');

$trySave($em, $quote, [
    'skipFormula' => true,
], '2) skipFormula (hook attivi)');

$fullOk = $trySave($em, $quote, [], '3) save completo (come UI)');

if ($fullOk) {
    $saved = $em->getEntityById('Quote', $quoteId);
    $savedStato = $saved ? (string) ($saved->get('statoFinanziamento') ?? '(vuoto)') : '(non ricaricato)';
    $out('');
    $out('statoFinanziamento dopo save: ' . $savedStato);
} else {
    $out('');
    $out('Se 1) OK ma 3) fallisce → problema in formula o hook.');
    $out('Se 1) fallisce → enum/DB/validazione core.');
}

$logDir = $crmRoot . '/data/logs';
$logCandidates = [
    $logDir . '/espo-' . date('Y-m-d') . '.log',
    $logDir . '/espo-' . date('Y-m-d', strtotime('-1 day')) . '.log',
    $logDir . '/espocrm.log',
];

$out('');
$out('=== Ultimi errori log (Quote / statoFinanziamento) ===');

foreach ($logCandidates as $logFile) {
    if (!is_readable($logFile)) {
        continue;
    }

    $lines = file($logFile, FILE_IGNORE_NEW_LINES);

    if ($lines === false) {
        continue;
    }

    $matches = array_values(array_filter(
        $lines,
        static fn (string $line): bool => stripos($line, 'quote') !== false
            || stripos($line, 'statoFinanziamento') !== false
            || stripos($line, 'SyncContractPricing') !== false
            || stripos($line, 'ERRORE') !== false
            || stripos($line, 'ERROR') !== false
            || stripos($line, 'CRITICAL') !== false
    ));

    $tail = array_slice($matches, -8);

    if ($tail === []) {
        continue;
    }

    $out('--- ' . basename($logFile) . ' ---');

    foreach ($tail as $line) {
        $out($line);
    }
}

if (!$fullOk) {
    exit(2);
}
