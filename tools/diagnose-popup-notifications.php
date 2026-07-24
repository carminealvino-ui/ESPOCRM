<?php

/**
 * Diagnostica popup + reminder + sample Call opportunity.
 *
 *   php tools/diagnose-popup-notifications.php
 *   php tools/diagnose-popup-notifications.php --user=67c93e694705fde80
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\AppuntamentoPendingCallCreator;
use Espo\Custom\Services\CallOpportunityLinker;
use Espo\Custom\Tools\Activities\PopupNotificationsProvider;
use Espo\Modules\Crm\Entities\Reminder;
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
$config = $container->get('config');

$user = $em->getEntityById('User', $userId);

if (!$user) {
    fwrite(STDERR, "User non trovato: {$userId}\n");
    exit(1);
}

echo "=== Diagnostica popup ===\n";
echo 'User: ' . $user->get('userName') . " ({$userId})\n";
echo 'config.useWebSocket=' . var_export((bool) $config->get('useWebSocket'), true) . "\n";

$meta = $container->get('metadata')->get(['app', 'popupNotifications', 'event']);
echo 'meta.useWebSocket=' . var_export($meta['useWebSocket'] ?? null, true) . "\n";
echo 'meta.view=' . ($meta['view'] ?? '') . "\n";
echo 'navbar badge=' . (
    $container->get('metadata')->get(['app', 'clientNavbar', 'items', 'notificationBadge', 'view'])
    ?? 'core'
) . "\n";

$badgePath = 'client/custom/src/views/notification/badge.js';
$badgeJs = is_file($badgePath) ? (string) file_get_contents($badgePath) : '';
echo 'badge ensurePopupContainerCompat=' . (str_contains($badgeJs, 'ensurePopupContainerCompat') ? 'YES' : 'NO') . "\n";
echo 'badge Parent.prototype.showPopupNotification=' . (
    str_contains($badgeJs, 'Parent.prototype.showPopupNotification') ? 'YES' : 'NO'
) . "\n\n";

$now = date('Y-m-d H:i:s');
$dueReminders = $em->getRDBRepository('Reminder')
    ->where([
        'type' => Reminder::TYPE_POPUP,
        'userId' => $userId,
        'remindAt<=' => $now,
    ])
    ->order('remindAt', 'DESC')
    ->limit(0, 15)
    ->find();

echo "Reminder popup due (remindAt<={$now}): " . count(iterator_to_array($dueReminders)) . "\n";

foreach ($dueReminders as $rem) {
    echo '  rem=' . $rem->getId()
        . ' ' . $rem->get('entityType')
        . ':' . $rem->get('entityId')
        . ' at=' . $rem->get('remindAt')
        . "\n";
}

echo "\n";

/** @var AppuntamentoPendingCallCreator $creator */
$creator = $factory->create(AppuntamentoPendingCallCreator::class);
/** @var CallOpportunityLinker $linker */
$linker = $factory->create(CallOpportunityLinker::class);

$plannedCalls = $em->getRDBRepository('Call')
    ->where([
        'status' => 'Planned',
        'assignedUserId' => $userId,
    ])
    ->order('dateStart', 'DESC')
    ->limit(0, 10)
    ->find();

echo "Call Pianificato (top 10):\n";

foreach ($plannedCalls as $call) {
    $show = $creator->shouldShowAutoPendingCallInPopup($call);
    $resolved = $linker->resolveOpportunityId($call);
    $remCount = $em->getRDBRepository('Reminder')
        ->where([
            'entityType' => 'Call',
            'entityId' => $call->getId(),
            'type' => Reminder::TYPE_POPUP,
            'userId' => $userId,
        ])
        ->count();

    echo '  ' . $call->getId()
        . ' | show=' . ($show ? '1' : '0')
        . ' | rem=' . $remCount
        . ' | opp=' . ($call->get('opportunityId') ?: 'NULL')
        . ' | resolve=' . ($resolved ?: 'NULL')
        . ' | ' . substr((string) $call->get('name'), 0, 55)
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
        $et = is_object($d) ? ($d->entityType ?? '?') : '?';
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
        $et = is_object($d) ? ($d->entityType ?? '?') : '?';
        $id = is_object($d) ? ($d->id ?? '?') : '?';
        $name = is_object($d) ? ($d->name ?? '') : '';
        echo "  - {$et} id={$id} " . substr((string) $name, 0, 55) . "\n";
        $n++;
    }
} catch (Throwable $e) {
    echo 'ERRORE provider->get: ' . $e->getMessage() . "\n";
    echo $e->getFile() . ':' . $e->getLine() . "\n";
    exit(1);
}

echo "\nFine.\n";
