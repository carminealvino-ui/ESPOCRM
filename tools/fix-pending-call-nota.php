#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Sposta il testo automatico da description a nota sulle Call Pending già create.
 * Lascia description vuota per compilare l'esito dopo la chiamata.
 *
 *   php tools/fix-pending-call-nota.php --dry-run
 *   php tools/fix-pending-call-nota.php
 */

$root = getenv('CRM_ROOT') ?: getcwd();

if (!is_file($root . '/bootstrap.php')) {
    fwrite(STDERR, "Eseguire da root CRM (es. ~/public_html/crm/mec-group).\n");
    exit(1);
}

require_once $root . '/bootstrap.php';

use Espo\Core\Application;

$argv = $GLOBALS['argv'] ?? [];
$dryRun = in_array('--dry-run', $argv, true);

$app = new Application();
$app->setupSystemUser();

$entityManager = $app->getContainer()->get('entityManager');

$collection = $entityManager
    ->getRDBRepository('Call')
    ->where([
        'nota*' => 'Auto-Pending-Appuntamento:',
    ])
    ->find();

$updated = 0;
$skipped = 0;

foreach ($collection as $call) {
    $nota = trim((string) $call->get('nota'));
    $description = trim((string) $call->get('description'));

    $needsNotaText = !str_contains($nota, 'Richiamo automatico per appuntamento Pending del');
    $needsClearDescription = $description !== '';

    if (!$needsNotaText && !$needsClearDescription) {
        $skipped++;
        continue;
    }

    $newNota = $nota;

    if ($needsNotaText && $description !== '') {
        $newNota = $nota === '' ? $description : $nota . "\n" . $description;
    }

    if ($dryRun) {
        echo "DRY-RUN Call {$call->getId()}\n";
        $updated++;
        continue;
    }

    $call->set([
        'nota' => $newNota,
        'description' => null,
    ]);

    $entityManager->saveEntity($call, [
        'skipAcl' => true,
        'silent' => true,
        'skipHooks' => true,
    ]);

    $updated++;
    echo "OK Call {$call->getId()}\n";
}

echo PHP_EOL;
echo "Aggiornate: {$updated}, già ok: {$skipped}";
echo $dryRun ? ' (dry-run)' : '';
echo PHP_EOL;
