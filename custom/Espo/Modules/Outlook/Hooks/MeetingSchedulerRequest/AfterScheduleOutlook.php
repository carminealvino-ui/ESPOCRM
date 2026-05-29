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

namespace Espo\Modules\Outlook\Hooks\MeetingSchedulerRequest;

use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Utils\Log;
use Espo\Modules\Crm\Entities\Meeting;
use Espo\Modules\Outlook\Core\Outlook\Exceptions\ApiError;
use Espo\Modules\Outlook\Tools\Calendar\Exceptions\NotEnabled;
use Espo\Modules\Outlook\Tools\Calendar\PushParams;
use Espo\Modules\Outlook\Tools\Calendar\Service;
use Espo\ORM\Entity;
use LogicException;

/**
 * @noinspection PhpUnused
 */
class AfterScheduleOutlook
{
    private const SERVICE = 'Microsoft';

    public function __construct(
        private Service $service,
        private Log $log,
    ) {}

    /**
     * @throws Forbidden
     * @throws Error
     * @noinspection PhpUnused
     */
    public function afterSchedule(Entity $entity): void
    {
        if (!method_exists($entity, 'getEvent')) {
            throw new LogicException();
        }

        if (!method_exists($entity, 'getScheduler')) {
            throw new LogicException();
        }

        $scheduler = $entity->getScheduler();
        $meeting = $entity->getEvent();

        if (!$scheduler instanceof Entity) {
            throw new LogicException("No scheduler.");
        }

        if (!$meeting instanceof Meeting) {
            throw new LogicException();
        }

        $service = $scheduler->get('externalService');

        if ($service !== self::SERVICE) {
            return;
        }

        if (!$meeting->getAssignedUser()) {
            return;
        }

        try {
            $this->service->push($meeting, new PushParams(onlineMeeting: true));
        } catch (NotEnabled|ApiError $e) {
            $this->log->error($e->getMessage(), ['exception' => $e]);
        }
    }
}
