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

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Modules\Crm\Entities\Meeting;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * @implements BeforeSave<Meeting>
 * @noinspection PhpUnused
 */
class SkipOutlookPush implements BeforeSave
{
    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if (
            !$options->get('fromMeetingSchedulerRequest') ||
            !$options->get('meetingSchedulerId')
        ) {
            return;
        }

        $scheduler = $this->entityManager->getEntityById('MeetingScheduler', $options->get('meetingSchedulerId'));

        if (!$scheduler || $scheduler->get('externalService') !== 'Microsoft') {
            return;
        }

        $entity->set('outlookSkipPush', true);
    }
}
