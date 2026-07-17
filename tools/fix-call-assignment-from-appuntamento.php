<?php

/**
 * Riallinea Call auto-pending: assegnatario dall'Appuntamento, Data Riscontro e promemoria popup.
 *
 *   cd ~/public_html/crm/mec-group
 *   php tools/fix-call-assignment-from-appuntamento.php
 */

require_once __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\AppuntamentoPendingCallCreator;
use Espo\Custom\Tools\DateTime\BusinessDateTime;
use Espo\ORM\Entity;

$app = new Application();
$app->setupSystemUser();

$entityManager = $app->getContainer()->get('entityManager');
$log = $app->getContainer()->get('log');
$creator = new AppuntamentoPendingCallCreator($entityManager, $log);

const NOTA_PENDING = 'Auto-Pending-Appuntamento:';
const NOTA_RICHIAMO = 'Auto-Richiamo-Appuntamento:';
const TIPOLOGIA_RICHIAMO = 'Richiamo su Opportunità Generata';
const LEGACY_TIPOLOGIA = 'Contatto dopo Prima Visita';

/** @var array<string, Entity> */
$callsById = [];

$queries = [
    ['nota*' => NOTA_PENDING],
    ['nota*' => NOTA_RICHIAMO],
    [
        'tipologia' => TIPOLOGIA_RICHIAMO,
        'whatsApp' => true,
    ],
    [
        'tipologia' => LEGACY_TIPOLOGIA,
        'whatsApp' => true,
    ],
];

foreach ($queries as $where) {
    $collection = $entityManager
        ->getRDBRepository('Call')
        ->where($where)
        ->find();

    foreach ($collection as $call) {
        if (!isAutoPendingCall($call)) {
            continue;
        }

        $callsById[$call->getId()] = $call;
    }
}

$total = count($callsById);
$planned = 0;
$held = 0;

foreach ($callsById as $call) {
    $status = (string) $call->get('status');

    if ($status === 'Planned') {
        $planned++;
    } elseif (in_array($status, ['Held', 'Not Held'], true)) {
        $held++;
    }
}

echo "Trovate {$total} Call auto-pending (Pianificato: {$planned}, Svolto/Non svolto: {$held})" . PHP_EOL;

if ($total === 0) {
    echo PHP_EOL
        . "Nessuna Call da riparare. Le Call già esitate (Svolto) non ricevono promemoria." . PHP_EOL
        . "Per verificare il fix: esita un nuovo appuntamento come Pending e controlla la Call Pianificata." . PHP_EOL;

    exit(0);
}

$updated = 0;
$skipped = 0;
$remindersSynced = 0;
$namesFixed = 0;
$namesStillBroken = 0;
$popupSuppressed = 0;
$duplicatesRemoved = 0;

foreach ($callsById as $call) {
    $nota = (string) $call->get('nota');
    $description = (string) $call->get('description');
    $changed = false;
    $oldName = (string) $call->get('name');

    if ($nota === '' && str_contains($description, 'Richiamo automatico per appuntamento Pending del')) {
        $call->set('nota', $description);
        $nota = $description;
        $changed = true;
    }

    $appuntamento = null;
    $appuntamentoId = extractAppuntamentoId($nota);

    if ($appuntamentoId) {
        $appuntamento = $entityManager->getEntityById('Appuntamento', $appuntamentoId);
    }

    if (!$appuntamento) {
        $appuntamento = $creator->findAppuntamentoForCall($call);
    }

    if ($appuntamento) {
        if ($creator->callNameMissingContact($oldName)) {
            $appuntamento = $creator->hydrateAppuntamentoContactLinks($appuntamento);
        }

        $ownerUserId = $creator->resolveOwnerUserId($appuntamento);

        if ($ownerUserId && (string) $call->get('assignedUserId') !== $ownerUserId) {
            $ownerUserName = $entityManager->getEntityById('User', $ownerUserId)?->get('name');

            $call->set([
                'assignedUserId' => $ownerUserId,
                'assignedUserName' => $ownerUserName,
                'usersIds' => [$ownerUserId],
                'usersNames' => $ownerUserName ? [$ownerUserId => $ownerUserName] : (object) [],
            ]);
            $changed = true;
        }

        if ($creator->syncCallNameFromAppuntamento($call, $appuntamento)) {
            $changed = true;
            $namesFixed++;
        } elseif ($creator->callNameMissingContact($oldName)
            && $creator->rebuildCallNameFromEntity($call)
        ) {
            $changed = true;
            $namesFixed++;
        }
    } elseif ($creator->rebuildCallNameFromEntity($call)) {
        $changed = true;
        $namesFixed++;
    }

    if ($creator->callNameMissingContact((string) $call->get('name'))) {
        $namesStillBroken++;
    }

    $status = (string) $call->get('status');

    if ($status === 'Planned' && $call->get('data')) {
        $call->set('data', null);
        $changed = true;
    }

    if (in_array($status, ['Held', 'Not Held'], true) && !$call->get('data')) {
        $today = (new \DateTimeImmutable('now', new \DateTimeZone(BusinessDateTime::BUSINESS_TIMEZONE)))
            ->format('Y-m-d');
        $call->set('data', $today);
        $changed = true;
    }

    if ($changed) {
        $entityManager->saveEntity($call, ['skipAcl' => true, 'silent' => true]);
        $updated++;
        echo 'OK ' . $call->getId() . ' (' . $status . ')' . PHP_EOL;
    } else {
        $skipped++;
    }

    if ($status === 'Planned') {
        if ($creator->shouldShowAutoPendingCallInPopup($call)) {
            $creator->syncPopupReminders($call);
            $remindersSynced++;
            echo 'REMINDER ' . $call->getId() . PHP_EOL;
        } else {
            $creator->clearPopupReminders($call);
            $popupSuppressed++;
            if ($creator->isAutoManagedRichiamoCall($call)) {
                $entityManager->removeEntity($call, ['skipAcl' => true]);
                $duplicatesRemoved++;
                echo 'DELETE DUPLICATE ' . $call->getId() . PHP_EOL;
            } else {
                echo 'SUPPRESS ' . $call->getId() . PHP_EOL;
            }
        }
    } else {
        $creator->clearPopupReminders($call);
    }
}

echo PHP_EOL . "Aggiornate: {$updated}, saltate: {$skipped}, nomi corretti: {$namesFixed}, nomi ancora senza contatto: {$namesStillBroken}, promemoria popup: {$remindersSynced}, popup soppressi (duplicati): {$popupSuppressed}, duplicati rimossi: {$duplicatesRemoved}" . PHP_EOL;

function isAutoPendingCall(Entity $call): bool
{
    $nota = (string) $call->get('nota');
    $description = (string) $call->get('description');
    $tipologia = trim((string) $call->get('tipologia'));
    $name = strtoupper(trim((string) $call->get('name')));

    if (str_contains($nota, NOTA_PENDING) || str_contains($nota, NOTA_RICHIAMO)) {
        return true;
    }

    if (str_contains($description, 'Richiamo automatico per appuntamento Pending del')) {
        return true;
    }

    if (!in_array($tipologia, [TIPOLOGIA_RICHIAMO, LEGACY_TIPOLOGIA], true)) {
        return false;
    }

    if (!(bool) $call->get('whatsApp')) {
        return false;
    }

    return str_contains($name, 'RICHIAMO SU OPPORTUNIT')
        || str_contains($name, 'CONTATTO DOPO PRIMA VISITA');
}

function extractAppuntamentoId(string $nota): ?string
{
    if (preg_match('/Auto-(?:Pending|Richiamo)-Appuntamento:\s*([a-z0-9]{17})/i', $nota, $matches)) {
        return $matches[1];
    }

    return null;
}
