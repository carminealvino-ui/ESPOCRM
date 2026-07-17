<?php

namespace Espo\Custom\Controllers;

use Espo\Controllers\Notification as BaseNotification;
use Espo\Custom\Tools\Notification\RecordService;

class Notification extends BaseNotification
{
    public function getActionNotReadCount(): int
    {
        $service = $this->injectableFactory->create(RecordService::class);

        return $service->getNotReadCount($this->user);
    }
}
