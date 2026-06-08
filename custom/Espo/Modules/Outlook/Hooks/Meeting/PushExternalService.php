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

namespace Espo\Modules\Outlook\Hooks\Meeting;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Core\Job\Job\Data;
use Espo\Core\Job\JobScheduler;
use Espo\Core\Job\QueueName;
use Espo\Modules\Crm\Entities\Meeting;
use Espo\Modules\Outlook\Tools\MeetingExternalService\Jobs\ExternalServicePushJob;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * @implements AfterSave<Meeting>
 * @implements BeforeSave<Meeting>
 */
class PushExternalService implements AfterSave, BeforeSave
{
    public const OPTION_SKIP_EXTERNAL_SERVICE = 'skipExternalService';

    public function __construct(
        private JobScheduler $jobScheduler,
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($entity->get('externalService') === 'Microsoft') {
            $entity->set('outlookSkipPush', true);
        }
    }

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if (
            $options->get(self::OPTION_SKIP_EXTERNAL_SERVICE) ||
            !$entity->isNew() ||
            $entity->get('externalService') !== 'Microsoft' ||
            !$entity->getAssignedUser()
        ) {
            return;
        }

        $this->jobScheduler
            ->setClassName(ExternalServicePushJob::class)
            ->setData(
                Data::create()
                    ->withTargetId($entity->getId())
                    ->withTargetType($entity->getEntityType())
            )
            ->setQueue(QueueName::Q0)
            ->schedule();

    }
}
