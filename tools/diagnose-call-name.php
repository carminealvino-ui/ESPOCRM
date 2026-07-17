<?php

/**
 * Diagnostica risoluzione nome contatto su una Call auto-pending.
 *
 *   php tools/diagnose-call-name.php <callId>
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\AppuntamentoPendingCallCreator;

$callId = null;

foreach (array_slice($argv ?? [], 1) as $arg) {
    if ($callId === null && $arg !== '') {
        $callId = $arg;
    }
}

if (!$callId) {
    fwrite(STDERR, "Uso: php tools/diagnose-call-name.php <callId>\n");
    exit(1);
}

$app = new Application();
$app->setupSystemUser();

$entityManager = $app->getContainer()->get('entityManager');
$log = $app->getContainer()->get('log');
$creator = new AppuntamentoPendingCallCreator($entityManager, $log);

echo 'Creator: ' . AppuntamentoPendingCallCreator::CREATOR_VERSION . PHP_EOL;

$call = $entityManager->getEntityById('Call', $callId);

if (!$call) {
    fwrite(STDERR, "Call non trovata: {$callId}\n");
    exit(1);
}

echo 'Call: ' . $callId . PHP_EOL;
echo 'name=' . (string) $call->get('name') . PHP_EOL;
echo 'telefono=' . (string) $call->get('telefono') . PHP_EOL;
echo 'parent=' . (string) $call->get('parentType') . '/' . (string) $call->get('parentId') . PHP_EOL;
echo 'prospectId=' . (string) $call->get('prospectId') . PHP_EOL;
echo 'nome senza contatto=' . ($creator->callNameMissingContact((string) $call->get('name')) ? 'sì' : 'no') . PHP_EOL;

$nota = (string) $call->get('nota');
$appuntamentoId = null;

if (preg_match('/Auto-(?:Pending|Richiamo)-Appuntamento:\s*([a-z0-9]{17})/i', $nota, $matches)) {
    $appuntamentoId = $matches[1];
}

echo 'appuntamentoId(nota)=' . ($appuntamentoId ?: 'null') . PHP_EOL;

$appuntamento = $appuntamentoId
    ? $entityManager->getEntityById('Appuntamento', $appuntamentoId)
    : null;

if (!$appuntamento) {
    $appuntamento = $creator->findAppuntamentoForCall($call);
}

if ($appuntamento) {
    echo 'Appuntamento: ' . $appuntamento->getId() . PHP_EOL;
    echo '  parentName=' . (string) $appuntamento->get('parentName') . PHP_EOL;
    echo '  prospectName=' . (string) $appuntamento->get('prospectName') . PHP_EOL;
    echo '  prospectId=' . (string) $appuntamento->get('prospectId') . PHP_EOL;
    echo '  parseContact=' . $creator->parseContactFromAppuntamento($appuntamento) . PHP_EOL;

    $hydrated = $creator->hydrateAppuntamentoContactLinks($appuntamento);
    echo '  dopo hydrate parentName=' . (string) $hydrated->get('parentName') . PHP_EOL;
    echo '  dopo hydrate prospectName=' . (string) $hydrated->get('prospectName') . PHP_EOL;

    $contact = $creator->resolveCallContactName($call, $hydrated);
    echo 'resolveCallContactName=' . ($contact !== '' ? $contact : '(vuoto)') . PHP_EOL;

    $wouldSync = $creator->syncCallNameFromAppuntamento($call, $hydrated);
    echo 'syncCallNameFromAppuntamento=' . ($wouldSync ? 'aggiornerebbe' : 'nessun cambio') . PHP_EOL;
    echo 'nuovo nome=' . (string) $call->get('name') . PHP_EOL;
} else {
    echo 'Appuntamento: non trovato' . PHP_EOL;
    $contact = $creator->resolveCallContactName($call);
    echo 'resolveCallContactName=' . ($contact !== '' ? $contact : '(vuoto)') . PHP_EOL;
    $wouldRebuild = $creator->rebuildCallNameFromEntity($call);
    echo 'rebuildCallNameFromEntity=' . ($wouldRebuild ? 'aggiornerebbe' : 'nessun cambio') . PHP_EOL;
    echo 'nuovo nome=' . (string) $call->get('name') . PHP_EOL;
}

exit(0);
