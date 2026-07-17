<?php

namespace Espo\Custom;

use Espo\Core\Binding\Binder;
use Espo\Core\Binding\BindingProcessor;
use Espo\Tools\Notification\RecordService as BaseNotificationRecordService;
use Espo\Custom\Tools\Notification\RecordService as CustomNotificationRecordService;

class Binding implements BindingProcessor
{
    public function process(Binder $binder): void
    {
        $binder->bindImplementation(
            BaseNotificationRecordService::class,
            CustomNotificationRecordService::class
        );
    }
}
