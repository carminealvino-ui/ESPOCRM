<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Allinea finanziamento / stati contratto da opportunità collegata.
 *
 * @implements BeforeSave<Entity>
 */
class SyncFinanziamentoFromOpportunity implements BeforeSave
{
    public static int $order = 15;

    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($entity->getEntityType() !== 'Quote') {
            return;
        }

        if ($options->has('silent') || $options->has('skipHooks')) {
            return;
        }

        $opportunityId = $entity->get('opportunityId');

        if (!$opportunityId) {
            return;
        }

        if (!$entity->isNew() && !$entity->isAttributeChanged('opportunityId')) {
            return;
        }

        $opportunity = $this->entityManager->getEntityById('Opportunity', $opportunityId);

        if (!$opportunity) {
            return;
        }

        $entity->set('finanziamento', (bool) $opportunity->get('finanziamento'));
        $entity->set('statoContratto', $opportunity->get('statoContratto'));
        $entity->set('statoFinanziamento', $opportunity->get('statoFinanziamento'));
    }
}
