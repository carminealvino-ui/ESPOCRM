<?php

namespace Espo\Custom\Tools\Notification;

use Espo\Core\Acl;
use Espo\Core\Select\SelectBuilderFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Metadata;
use Espo\Entities\Notification;
use Espo\Entities\User;
use Espo\ORM\EntityManager;
use Espo\Tools\Notification\RecordService as BaseRecordService;
use Espo\Tools\Stream\NoteAccessControl;
use Espo\Tools\User\PreferencesProvider;
use UnexpectedValueException;

/**
 * EspoCRM 10.0: getNotReadCount() fa is_int() sul risultato COUNT.
 * Con MySQL/PDO il valore arriva spesso come stringa numerica → UnexpectedValueException.
 */
class RecordService extends BaseRecordService
{
    public function __construct(
        private EntityManager $entityManager,
        Acl $acl,
        Metadata $metadata,
        NoteAccessControl $noteAccessControl,
        SelectBuilderFactory $selectBuilderFactory,
        Config $config,
        PreferencesProvider $preferencesProvider,
    ) {
        parent::__construct(
            $entityManager,
            $acl,
            $metadata,
            $noteAccessControl,
            $selectBuilderFactory,
            $config,
            $preferencesProvider,
        );
    }

    public function getNotReadCount(User $user): int
    {
        try {
            return parent::getNotReadCount($user);
        } catch (UnexpectedValueException) {
            return $this->countUnreadFallback($user);
        }
    }

    private function countUnreadFallback(User $user): int
    {
        $userId = $user->getId();

        if (!$userId) {
            return 0;
        }

        return (int) $this->entityManager
            ->getRDBRepositoryByClass(Notification::class)
            ->where([
                'userId' => $userId,
                'read' => false,
            ])
            ->count();
    }
}
