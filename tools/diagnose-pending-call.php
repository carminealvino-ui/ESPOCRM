#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Diagnostica creazione Call da Appuntamento Pending.
 *
 *   php tools/diagnose-pending-call.php --id=APPUNTAMENTO_ID
 *   php tools/diagnose-pending-call.php --id=APPUNTAMENTO_ID --create
 */

$root = getenv('CRM_ROOT') ?: getcwd();

if (!is_file($root . '/bootstrap.php')) {
    fwrite(STDERR, "Eseguire da root CRM (es. ~/public_html/crm/mec-group).\n");
    exit(1);
}

require_once $root . '/bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\AppuntamentoPendingCallCreator;

$argv = $GLOBALS['argv'] ?? [];
$id = null;
$create = in_array('--create', $argv, true);

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--id=')) {
        $id = substr($arg, 5);
    }
}

if (!$id) {
    fwrite(STDERR, "Uso: php tools/diagnose-pending-call.php --id=APPUNTAMENTO_ID [--create]\n");
    exit(1);
}

$app = new Application();
$app->setupSystemUser();

$container = $app->getContainer();
$entityManager = $container->get('entityManager');
$log = $container->get('log');

$appuntamento = $entityManager->getEntityById('Appuntamento', $id);

if (!$appuntamento) {
    fwrite(STDERR, "Appuntamento non trovato: {$id}\n");
    exit(1);
}

$nota = 'Auto-Pending-Appuntamento: ' . $id;
$existingCall = $entityManager
    ->getRDBRepository('Call')
    ->where(['nota' => $nota])
    ->findOne();

echo "Appuntamento {$id}\n";
echo '  status: ' . ($appuntamento->get('status') ?: '(vuoto)') . "\n";
echo '  sottostato: ' . ($appuntamento->get('sottostato') ?: '(vuoto)') . "\n";
echo '  dateStart: ' . ($appuntamento->get('dateStart') ?: '(vuoto)') . "\n";
echo '  parentType: ' . ($appuntamento->get('parentType') ?: '(vuoto)') . "\n";
echo '  parentId: ' . ($appuntamento->get('parentId') ?: '(vuoto)') . "\n";
echo '  prospectId: ' . ($appuntamento->get('prospectId') ?: '(vuoto)') . "\n";
echo '  call esistente: ' . ($existingCall ? $existingCall->getId() : 'no') . "\n";

$hookFile = $root . '/custom/Espo/Custom/Hooks/Appuntamento/AutoCreatePendingCall.php';
$hooksMeta = $root . '/custom/Espo/Custom/Resources/metadata/hooks/Appuntamento.json';

echo '  hook file: ' . (is_file($hookFile) ? 'OK' : 'MANCANTE') . "\n";

if (is_file($hooksMeta)) {
    $meta = json_decode((string) file_get_contents($hooksMeta), true);
    $registered = isset($meta['afterSave']['autoCreatePendingCall']);
    echo '  hook metadata: ' . ($registered ? 'OK' : 'MANCANTE') . "\n";
} else {
    echo "  hook metadata: MANCANTE\n";
}

if (!$create) {
    echo "\nPer provare la creazione: php tools/diagnose-pending-call.php --id={$id} --create\n";
    exit(0);
}

$creator = new AppuntamentoPendingCallCreator($entityManager, $log);

try {
    $callId = $creator->createIfNeeded($appuntamento);

    if ($callId) {
        echo "\nOK: creata Call {$callId}\n";
        exit(0);
    }

    echo "\nSKIP: nessuna Call creata (vedi log Espo per dettagli).\n";
    exit(2);
} catch (Throwable $e) {
    fwrite(STDERR, "\nERRORE: {$e->getMessage()}\n");
    exit(1);
}
