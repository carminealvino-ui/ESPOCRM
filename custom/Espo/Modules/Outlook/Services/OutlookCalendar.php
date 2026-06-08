<?php
/***********************************************************************************
 * The contents of this file are subject to the Extension License Agreement
 * ("Agreement") which can be viewed at
 * https://www.espocrm.com/extension-license-agreement/.
 * By copying, installing downloading, or using this file, You have unconditionally
 * agreed to the terms and conditions of the Agreement, and You may not use this
 * file except in compliance with the Agreement. Under the terms of the Agreement,
 * You shall not license, sublicense, sell, resell, rent, lease, lend, distribute,
 * redistribute, market, publish, commercialize, or otherwise transfer rights or
 * usage to the software or any modified version or derivative work of the software
 * created by or for you.
 *
 * Copyright (C) 2015-2026 EspoCRM, Inc.
 *
 * License ID: 77350457a8d35522431c4daeee1dd4ad
 ************************************************************************************/

namespace Espo\Modules\Outlook\Services;

use DateTime;
use Espo\Core\AclManager;
use Espo\Core\Exceptions\Error;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Language;
use Espo\Entities\ExternalAccount;
use Espo\Entities\Notification;
use Espo\Entities\User;
use Espo\Modules\Outlook\Core\Outlook\CalendarManager;
use Espo\Modules\Outlook\Core\Outlook\Exceptions\ApiError;
use Espo\Modules\Outlook\Entities\OutlookCalendarUser;
use Espo\ORM\EntityManager;
use Espo\Services\ExternalAccount as ExternalAccountService;
use RuntimeException;

class OutlookCalendar
{
    public function __construct(
        private EntityManager $entityManager,
        private InjectableFactory $injectableFactory,
        private Language $defaultLanguage,
        private User $user,
        private AclManager $aclManager,
        private CalendarManager $calendarManager,
    ) {}

    /**
     * @return object
     * @throws Error
     */
    public function usersCalendars()
    {
        return $this->calendarManager->getCalendarList($this->user->getId());
    }

    /**
     * @throws Error
     * @throws ApiError
     */
    public function syncCalendarToMicrosoft(OutlookCalendarUser $calendarUser): void
    {
        $externalAccount = $this->getExternalAccountForSync($calendarUser);

        $this->calendarManager->syncCalendarToMicrosoft($calendarUser, $externalAccount);
    }

    /**
     * @throws Error
     * @throws ApiError
     */
    public function syncCalendarToEspo(OutlookCalendarUser $calendarUser): void
    {
        $externalAccount = $this->getExternalAccountForSync($calendarUser);

        $this->calendarManager->syncCalendarToEspo($calendarUser, $externalAccount);
    }

    private function getExternalAccountForSync(OutlookCalendarUser $calendarUser): ?ExternalAccount
    {
        $userId = $calendarUser->getUserId();

        if (!$userId) {
            return null;
        }

        $user = $this->entityManager->getRDBRepositoryByClass(User::class)->getById($userId);

        if (!$user) {
            return null;
        }

        $externalAccount = $this->entityManager
            ->getRDBRepositoryByClass(ExternalAccount::class)->getById('Outlook__' . $userId);

        if (!$externalAccount) {
            return null;
        }

        if (!$externalAccount->isEnabled() || !$externalAccount->get('outlookCalendarEnabled')) {
            return null;
        }

        if (!$externalAccount->get('calendarDirection')) {
            return null;
        }

        if (!$this->aclManager->check($user, 'OutlookCalendar')) {
            return null;
        }

        $service = $this->injectableFactory->create(ExternalAccountService::class);

        $isConnected = $service->ping('Outlook', $userId);

        if (!$isConnected) {
            $n = $this->entityManager
                ->getRDBRepository(Notification::ENTITY_TYPE)
                ->where([
                    'relatedType' => $externalAccount->getEntityType(),
                    'createdAt>=' => (new DateTime())->modify('-1 day')->format('Y-m-d H:i:s'),
                    'userId' => $userId,
                ])
                ->select(['id'])
                ->findOne();

            if (!$n) {
                $this->entityManager->createEntity(Notification::ENTITY_TYPE, [
                    'type' => 'System',
                    'message' => $this->defaultLanguage
                        ->translate('calendarConnectionProblem', 'messages', 'OutlookCalendar'),
                    'userId' => $userId,
                    'relatedType' => $externalAccount->getEntityType(),
                ]);
            }

            $GLOBALS['log']
                ->error('Outlook Calendar Sync: ' . $calendarUser->get('userName') .
                    ' could not connect to Outlook Server while trying to sync calendar ' .
                    $calendarUser->get('calendarName') . '.');

            return null;
        }

        if (!$this->aclManager->checkScope($user, 'OutlookCalendar')) {
            $GLOBALS['log']->info("Outlook Calendar Sync: Access forbidden for user $userId.");

            return null;
        }

        /** @var ?ExternalAccount $externalAccount */
        $externalAccount = $this->entityManager->getEntityById(ExternalAccount::ENTITY_TYPE, $externalAccount->getId());

        if (!$externalAccount) {
            throw new RuntimeException();
        }

        return $externalAccount;
    }
}
