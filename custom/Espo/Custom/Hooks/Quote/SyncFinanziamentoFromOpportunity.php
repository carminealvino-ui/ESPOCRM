<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Allinea finanziamento e stati contratto da opportunità collegata.
 *
 * @implements BeforeSave<Entity>
 */
class SyncFinanziamentoFromOpportunity implements BeforeSave
{
    public static int $order = 4;

    /** @var list<string> */
    private const SYNC_FIELDS = [
        'finanziamento',
        'statoFinanziamento',
        'importoFinanziato',
        'rataPrestito',
        'nrRate',
        'tassoZero',
    ];

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

        $opportunity = $this->entityManager->getEntityById('Opportunity', $opportunityId);

        if (!$opportunity) {
            return;
        }

        $forceAll = $entity->isNew() || $entity->isAttributeChanged('opportunityId');
        $needsFill = $this->quoteNeedsFinanziamentoFromOpportunity($entity, $opportunity);

        if (!$forceAll && !$needsFill) {
            return;
        }

        foreach (self::SYNC_FIELDS as $field) {
            if (!$forceAll && !$this->isEmptyQuoteField($entity, $field)) {
                continue;
            }

            $value = $opportunity->get($field);

            if ($field === 'finanziamento' || $field === 'tassoZero') {
                $entity->set($field, (bool) $value);
                continue;
            }

            if ($value !== null && $value !== '') {
                $entity->set($field, $value);
            }
        }
    }

    private function isEmptyQuoteField(Entity $entity, string $field): bool
    {
        $value = $entity->get($field);

        if ($field === 'finanziamento' || $field === 'tassoZero') {
            return $value === null;
        }

        if ($value === null || $value === '') {
            return true;
        }

        if (is_numeric($value) && (float) $value === 0.0) {
            return in_array($field, ['importoFinanziato', 'rataPrestito', 'nrRate'], true);
        }

        return false;
    }

    private function quoteNeedsFinanziamentoFromOpportunity(Entity $entity, Entity $opportunity): bool
    {
        if ((bool) $opportunity->get('finanziamento') && !(bool) $entity->get('finanziamento')) {
            return true;
        }

        foreach (['statoFinanziamento', 'importoFinanziato', 'rataPrestito', 'nrRate'] as $field) {
            $fromOpportunity = $opportunity->get($field);

            if ($fromOpportunity !== null && $fromOpportunity !== '' && $this->isEmptyQuoteField($entity, $field)) {
                return true;
            }
        }

        return false;
    }
}
