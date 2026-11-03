<?php
namespace Espo\Custom\Hooks\Opportunity;
use Espo\Core\Hook\Hook\AfterSave;
use Espo\Custom\Services\CallLinkedIdentity;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;
class SyncCalls implements AfterSave
{
    public static int $order=10020;
    public function __construct(private CallLinkedIdentity $sync) {}
    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if($options->get('skipHooks'))return;
        if($entity->isNew()||$entity->isAttributeChanged('appuntamentoId')||$entity->isAttributeChanged('prospectId')||$entity->isAttributeChanged('leadId'))$this->sync->syncOpportunity($entity);
    }
}
