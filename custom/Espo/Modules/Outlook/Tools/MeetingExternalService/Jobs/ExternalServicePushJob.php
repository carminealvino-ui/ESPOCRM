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

namespace Espo\Modules\Outlook\Tools\MeetingExternalService\Jobs;

use Espo\Core\Exceptions\Error;
use Espo\Core\Job\Job;
use Espo\Core\Job\Job\Data;
use Espo\Modules\Crm\Entities\Meeting;
use Espo\Modules\Outlook\Core\Outlook\Exceptions\ApiError;
use Espo\Modules\Outlook\Tools\Calendar\Exceptions\NotEnabled;
use Espo\Modules\Outlook\Tools\Calendar\PushParams;
use Espo\Modules\Outlook\Tools\Calendar\Service;
use Espo\ORM\EntityManager;
use RuntimeException;

class ExternalServicePushJob implements Job
{
    public function __construct(
        private EntityManager $entityManager,
        private Service $service,
    ) {}

    public function run(Data $data): void
    {
        $id = $data->getTargetId() ?? throw new RuntimeException();

        $meeting = $this->entityManager->getRDBRepositoryByClass(Meeting::class)->getById($id);

        if (!$meeting) {
            throw new RuntimeException("No record found.");
        }

        try {
            $this->service->push($meeting, new PushParams(onlineMeeting: true));
        } catch (ApiError|NotEnabled|Error $e) {
            throw new RuntimeException("Microsoft meeting push error.", ['exception' => $e]);
        }
    }
}
