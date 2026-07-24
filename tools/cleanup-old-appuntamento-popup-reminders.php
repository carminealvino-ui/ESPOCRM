<?php

/**
 * Rimuove Reminder popup su Appuntamento fuori finestra (vecchi / non eleggibili).
 *
 *   php tools/cleanup-old-appuntamento-popup-reminders.php
 *   php tools/cleanup-old-appuntamento-popup-reminders.php --apply
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Tools\Appuntamento\PendingCallDateTime;
use Espo\Modules\Crm\Entities\Reminder;

$apply = in_array('--apply', $argv ?? [], true);

$app = new Application();
$app->setupSystemUser();
$em = $app->getContainer()->get('entityManager');

$floor = PendingCallDateTime::popupEligibilityFloor();
$cutoff = PendingCallDateTime::popupEligibilityCutoff();

echo "=== Cleanup Reminder popup Appuntamento fuori finestra ===\n";
echo "Floor (min dateStart): {$floor}\n";
echo "Cutoff (max dateStart): {$cutoff}\n";
echo "Modalità: " . ($apply ? 'APPLICA' : 'ANTEPRIMA') . "\n\n";

$reminders = $em->getRDBRepository('Reminder')
    ->where([
        'type' => Reminder::TYPE_POPUP,
        'entityType' => 'Appuntamento',
    ])
    ->limit(0, 2000)
    ->find();

$would = 0;
$done = 0;

foreach ($reminders as $reminder) {
    $entityId = (string) $reminder->get('entityId');
    $appt = $entityId !== '' ? $em->getEntityById('Appuntamento', $entityId) : null;

    $remove = false;
    $reason = '';

    if (!$appt) {
        $remove = true;
        $reason = 'appuntamento mancante';
    }
    elseif (!PendingCallDateTime::isAppuntamentoPopupEligible(
        $appt->get('dateStart') ? (string) $appt->get('dateStart') : null
    )) {
        $remove = true;
        $reason = 'fuori finestra dateStart=' . ($appt->get('dateStart') ?? '');
    }
    elseif ((string) $appt->get('status') !== 'Planned') {
        $remove = true;
        $reason = 'status=' . ($appt->get('status') ?? '');
    }

    if (!$remove) {
        continue;
    }

    $would++;
    echo ($apply ? 'DEL ' : 'WOULD ')
        . $reminder->getId()
        . ' App.' . $entityId
        . ' | ' . $reason
        . "\n";

    if ($apply) {
        $em->removeEntity($reminder, ['skipAcl' => true, 'silent' => true]);
        $done++;
    }
}

echo "\n";
echo ($apply ? "Rimossi: {$done}\n" : "Da rimuovere: {$would}\n");

if (!$apply) {
    echo "Per applicare: php tools/cleanup-old-appuntamento-popup-reminders.php --apply\n";
}
