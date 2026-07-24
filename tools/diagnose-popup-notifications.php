<?php

/**
 * Diagnostica popup promemoria (richiami Call + esito Appuntamento).
 *
 *   php tools/diagnose-popup-notifications.php
 *   php tools/diagnose-popup-notifications.php --user=67c93e694705fde80
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\AppuntamentoPendingCallCreator;
use Espo\Custom\Tools\Activities\PopupNotificationsProvider;
use Espo\ORM\EntityManager;
use Throwable;

$userId = '67c93e694705fde80';

foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--user=')) {
        $userId = substr($arg, 7);
    }
}

$app = new Application();
$app->setupSystemUser();

$container = $app->getContainer();
/** @var EntityManager $em */
$em = $container->get('entityManager');
$factory = $container->get('injectableFactory');

$user = $em->getEntityById('User', $userId);

if (!$user) {
    fwrite(STDERR, "User non trovato: {$userId}\n");
    exit(1);
}

echo '=== Diagnostica popup ===' . "\n";
echo 'User: ' . $user->get('userName') . ' (' . $userId . ')' . "\n";

$meta = $container->get('metadata')->get(['app', 'popupNotifications', 'event']);
echo 'useWebSocket=' . var_export($meta['useWebSocket'] ?? null, true) . "\n";
echo 'navbar badge view=' . ($container->get('metadata')->get(['app', 'clientNavbar', 'items', 'notificationBadge', 'view']) ?? 'core') . "\n\n";

$plannedCalls = $em->getRDBRepository('Call')
    ->where([
        'status' => 'Planned',
        'assignedUserId' => $userId,
    ])
    ->order('dateStart', 'DESC')
    ->limit(0, 10)
    ->find();

echo 'Call Pianificato (top 10 assegnate): ' . $plannedCalls->count() . "\n";

/** @var AppuntamentoPendingCallCreator $creator */
$creator = $factory->create(AppuntamentoPendingCallCreator::class);

foreach ($plannedCalls as $call) {
    $show = $creator->shouldShowAutoPendingCallInPopup($call);
    $remCount = $em->getRDBRepository('Reminder')
        ->where([
            'entityType' => 'Call',
            'entityId' => $call->getId(),
            'type' => 'popup',
        ])
        ->count();
    echo '  ' . $call->getId()
        . ' | show=' . ($show ? '1' : '0')
        . ' | rem=' . $remCount
        . ' | ' . substr((string) $call->get('name'), 0, 50)
        . ' | start=' . ($call->get('dateStart') ?? '')
        . "\n";
}

echo "\n";

try {
    /** @var PopupNotificationsProvider $provider */
    $provider = $factory->create(PopupNotificationsProvider::class);
    $items = $provider->get($user);
    $by = [];

    foreach ($items as $it) {
        $d = $it->getData();
        $et = $d['entityType'] ?? '?';
        $by[$et] = ($by[$et] ?? 0) + 1;
    }

    echo 'Provider->get total=' . count($items) . "\n";

    foreach ($by as $k => $v) {
        echo "  {$k}: {$v}\n";
    }

    $n = 0;

    foreach ($items as $it) {
        if ($n >= 8) {
            break;
        }

        $d = $it->getData();
        echo '  - ' . ($d['entityType'] ?? '?')
            . ' id=' . ($d['id'] ?? '?')
            . ' ' . substr((string) ($d['entityName'] ?? $d['name'] ?? ''), 0, 55)
            . "\n";
        $n++;
    }
} catch (Throwable $e) {
    echo 'ERRORE provider->get: ' . $e->getMessage() . "\n";
    echo $e->getFile() . ':' . $e->getLine() . "\n";
    exit(1);
}

echo "\nFine.\n";
