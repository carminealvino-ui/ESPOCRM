<?php

namespace Espo\Custom\Hooks\Opportunity;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Custom\Services\ProvvigioneStatusSync;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Allinea stato provvigioni e date pagamento al variare dello stato contratto.
 */
class SyncProvvigioniStato implements AfterSave
{
    public static int $order = 20;

    public function __construct(
        private EntityManager $entityManager,
        private ProvvigioneStatusSync $statusSync
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('silent') || $options->get('skipHooks')) {
            return;
        }

        if (!$entity->getId()) {
            return;
        }

        $watch = [
            'statoContratto',
            'installazione',
            'importoCaparra',
            'importoOpportunit',
            'amount',
            'closeDate',
        ];

        $changed = $entity->isNew();

        foreach ($watch as $field) {
            if ($entity->isAttributeChanged($field)) {
                $changed = true;
                break;
            }
        }

        if (!$changed) {
            return;
        }

        $quotes = $this->entityManager
            ->getRDBRepository('Quote')
            ->where(['opportunityId' => $entity->getId()])
            ->find();

        foreach ($quotes as $quote) {
            $this->statusSync->syncProvvigioniForQuote($quote, $entity);
        }
    }
}
