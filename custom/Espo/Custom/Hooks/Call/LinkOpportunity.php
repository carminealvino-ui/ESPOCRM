<?php

namespace Espo\Custom\Hooks\Call;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Custom\Services\CallOpportunityLinker;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Collega il Contatto Telefonico all'Opportunity correlata.
 */
class LinkOpportunity implements BeforeSave
{
    public static int $order = 8;

    public function __construct(
        private CallOpportunityLinker $linker,
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks')) {
            return;
        }

        if ($entity->getEntityType() !== 'Call') {
            return;
        }

        if ($entity->get('opportunityId')) {
            return;
        }

        $this->linker->linkOnCall($entity);
    }
}
