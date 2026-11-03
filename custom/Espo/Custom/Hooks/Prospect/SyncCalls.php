<?php
namespace Espo\Custom\Hooks\Prospect;
use Espo\Core\Hook\Hook\AfterSave;
use Espo\Custom\Services\CallLinkedIdentity;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;
class SyncCalls implements AfterSave
{
    public static int $order=10010;
    public function __construct(private CallLinkedIdentity $sync) {}
    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if($options->get('skipHooks'))return;
        foreach(['name','firstName','lastName','phoneNumber','phoneNumberData'] as $field)if($entity->isAttributeChanged($field)){$this->sync->syncProspect($entity);return;}
    }
}
