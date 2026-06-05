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

namespace Espo\Modules\Outlook\Jobs;

use Espo\Core\Job\JobDataLess;
use Espo\Core\Utils\Log;
use Espo\Entities\Integration;
use Espo\Modules\Outlook\Entities\OutlookCalendarUser;
use Espo\Modules\Outlook\Services\OutlookCalendar;
use Espo\ORM\EntityManager;
use Throwable;

/**
 * @noinspection PhpUnused
 */
class SyncOutlookCalendar implements JobDataLess
{
    public function __construct(
        private EntityManager $entityManager,
        private OutlookCalendar $service,
        private Log $log,
    ) {}

    public function run(): void
    {
        $integration = $this->entityManager->getRDBRepositoryByClass(Integration::class)->getById('Outlook');

        if (!$integration || !$integration->isEnabled()) {
            return;
        }

        $itemList = $this->entityManager
            ->getRDBRepositoryByClass(OutlookCalendarUser::class)
            ->join('user')
            ->where([
                'active' => true,
                'user.isActive' => true,
            ])
            ->find();

        foreach ($itemList as $item) {
            try {
                $this->service->syncCalendarToMicrosoft($item);
            } catch (Throwable $e) {
                $this->log->error('Outlook Calendar sync (out), user: {userId}; {message}', [
                    'exception' => $e,
                    'userId' => $item->getUserId(),
                    'message' => $e->getMessage(),
                ]);
            }
        }

        foreach ($itemList as $item) {
            try {
                $this->service->syncCalendarToEspo($item);
            } catch (Throwable $e) {
                $this->log->error('Outlook Calendar sync (in), user: {userId}; {message}', [
                    'exception' => $e,
                    'userId' => $item->getUserId(),
                    'message' => $e->getMessage(),
                ]);
            }
        }
    }
}
