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

require_once $root . '/custom/Espo/Custom/Tools/Appuntamento/PendingCallDateTime.php';

use Espo\Core\Application;
use Espo\Custom\Services\AppuntamentoPendingCallCreator;
use Espo\Custom\Tools\Appuntamento\PendingCallDateTime;

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
$config = $container->get('config');
$creator = new AppuntamentoPendingCallCreator($entityManager, $log, $config);

$appuntamento = $entityManager->getEntityById('Appuntamento', $id);

if (!$appuntamento) {
    fwrite(STDERR, "Appuntamento non trovato: {$id}\n");
    exit(1);
}

$notaMarker = 'Auto-Pending-Appuntamento: ' . $id;
$existingCall = $entityManager
    ->getRDBRepository('Call')
    ->where(['nota*' => $notaMarker])
    ->findOne();

echo "Appuntamento {$id}\n";
echo '  status: ' . ($appuntamento->get('status') ?: '(vuoto)') . "\n";
echo '  sottostato: ' . ($appuntamento->get('sottostato') ?: '(vuoto)') . "\n";
echo '  dateStart: ' . ($appuntamento->get('dateStart') ?: '(vuoto)') . "\n";
echo '  parentType: ' . ($appuntamento->get('parentType') ?: '(vuoto)') . "\n";
echo '  parentId: ' . ($appuntamento->get('parentId') ?: '(vuoto)') . "\n";
echo '  prospectId: ' . ($appuntamento->get('prospectId') ?: '(vuoto)') . "\n";
echo '  assignedUserId: ' . ($appuntamento->get('assignedUserId') ?: '(vuoto)') . "\n";
$assignedUsersIds = $appuntamento->get('assignedUsersIds') ?: [];
echo '  assignedUsersIds: ' . ($assignedUsersIds === [] ? '(vuoto)' : implode(', ', $assignedUsersIds)) . "\n";
echo '  owner Call atteso: ' . ($creator->resolveOwnerUserId($appuntamento) ?: '(vuoto)') . "\n";
$expectedCallDate = $creator->buildExpectedCallDateStart($appuntamento);
echo '  dateStart Call atteso (tz app): ' . ($expectedCallDate ?: '(vuoto)') . "\n";
$callInstant = $creator->buildCallInstantFromAppointment($appuntamento);
if ($callInstant) {
    echo '  ora Call attesa (Rome): ' . PendingCallDateTime::formatBusinessDateTime($callInstant) . "\n";
}
echo '  timezone applicazione: ' . ($config->get('timeZone') ?: PendingCallDateTime::BUSINESS_TIMEZONE) . "\n";
echo '  call esistente: ' . ($existingCall ? $existingCall->getId() : 'no') . "\n";
if ($existingCall) {
    echo '  call dateStart DB: ' . ($existingCall->get('dateStart') ?: '(vuoto)') . "\n";
    echo '  call assignedUserId: ' . ($existingCall->get('assignedUserId') ?: '(vuoto)') . "\n";
}

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

$formulaMeta = $root . '/custom/Espo/Custom/Resources/metadata/formula/Call.json';

if (is_file($formulaMeta)) {
    $formulaRaw = (string) file_get_contents($formulaMeta);
    $hasPendingGuard = str_contains($formulaRaw, 'Auto-Pending-Appuntamento:');
    $hasDateStartGuard = str_contains($formulaRaw, '!empty(dateStart)');
    echo '  formula Call guard Pending: ' . ($hasPendingGuard ? 'OK' : 'MANCANTE') . "\n";
    echo '  formula Call guard dateStart: ' . ($hasDateStartGuard ? 'OK' : 'MANCANTE') . "\n";
} else {
    echo "  formula Call.json: MANCANTE\n";
}

$creatorFile = $root . '/custom/Espo/Custom/Services/AppuntamentoPendingCallCreator.php';

if (is_file($creatorFile)) {
    $creatorRaw = (string) file_get_contents($creatorFile);
    $usesAssignedUsers = str_contains($creatorRaw, 'assignedUsersIds');
    $usesEventRepo = !str_contains($creatorRaw, "'skipHooks' => true");
    echo '  creator assignedUsersIds: ' . ($usesAssignedUsers ? 'OK' : 'MANCANTE') . "\n";
    echo '  creator usa repository Event (no skipHooks): ' . ($usesEventRepo ? 'OK' : 'MANCANTE') . "\n";
} else {
    echo "  creator: MANCANTE\n";
}

if (!$create) {
    echo "\nPer provare la creazione: php tools/diagnose-pending-call.php --id={$id} --create\n";
    exit(0);
}

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
