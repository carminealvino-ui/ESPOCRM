#!/usr/bin/env php
<?php
/**
 * Test diagnostico CreateContratto da CLI (mostra stack trace completo).
 *
 *   php tools/test-create-contratto.php --id=OPPORTUNITY_ID
 *   php tools/test-create-contratto.php --id=OPPORTUNITY_ID --dry-run
 *
 * --dry-run: valida dati e mostra preview senza salvare il contratto.
 */
declare(strict_types=1);

$root = getenv('CRM_ROOT') ?: getcwd();

if (!is_file($root . '/bootstrap.php')) {
    fwrite(STDERR, "Eseguire da root CRM (directory con bootstrap.php).\n");
    exit(1);
}

require_once $root . '/bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Actions\Opportunity\CreateContratto;

$app = new Application();
$app->setupSystemUser();

$em = $app->getContainer()->get('entityManager');

$id = null;
$dryRun = in_array('--dry-run', $argv, true);

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--id=')) {
        $id = substr($arg, 5);
    }
}

if (!$id) {
    fwrite(STDERR, "Usage: php tools/test-create-contratto.php --id=OPPORTUNITY_ID [--dry-run]\n");
    exit(1);
}

$opportunity = $em->getEntityById('Opportunity', $id);

if (!$opportunity) {
    fwrite(STDERR, "Opportunità non trovata: {$id}\n");
    exit(1);
}

fwrite(STDOUT, "=== Test CreateContratto ===\n");
fwrite(STDOUT, 'ID: ' . $id . "\n");
fwrite(STDOUT, 'Nome: ' . ($opportunity->get('name') ?? '') . "\n");
fwrite(STDOUT, 'Stage: ' . ($opportunity->get('stage') ?? '') . "\n");
fwrite(STDOUT, 'Lead: ' . ($opportunity->get('leadName') ?? '(nessuno)') . "\n");
fwrite(STDOUT, 'AccountId: ' . ($opportunity->get('accountId') ?? '(vuoto)') . "\n");
fwrite(STDOUT, 'ProspectId: ' . ($opportunity->get('prospectId') ?? '(vuoto)') . "\n");
fwrite(STDOUT, 'Importo: ' . ($opportunity->get('amount') ?? '(vuoto)') . "\n\n");

$existing = $em->getRDBRepository('Quote')
    ->where(['opportunityId' => $id])
    ->findOne();

if ($existing) {
    fwrite(STDOUT, "Contratto già esistente: {$existing->getId()} — {$existing->get('name')}\n");
    fwrite(STDOUT, "CreateContratto restituirebbe existing=true (nessun errore atteso).\n");
    exit(0);
}

if ($dryRun) {
    fwrite(STDOUT, "DRY-RUN: nessun save eseguito. Rimuovi --dry-run per creare il contratto.\n");
    exit(0);
}

try {
    $action = new CreateContratto($em);
    $result = $action->run($opportunity);

    fwrite(STDOUT, "OK\n");
    fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");
} catch (\Throwable $e) {
    fwrite(STDERR, 'ERRORE: ' . $e->getMessage() . "\n\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(1);
}
