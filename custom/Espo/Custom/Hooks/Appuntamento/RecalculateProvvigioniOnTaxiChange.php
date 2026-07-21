<?php

namespace Espo\Custom\Hooks\Appuntamento;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Custom\Services\ProvvigioneManager;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Ricalcola le provvigioni consolidate dei contratti collegati quando cambia il flag Taxi.
 *
 * @implements AfterSave<Entity>
 */
class RecalculateProvvigioniOnTaxiChange implements AfterSave
{
    public static int $order = 20;

    public function __construct(
        private EntityManager $entityManager,
        private ProvvigioneManager $provvigioneManager
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks') || $options->get('silent') || $options->get('isImport')) {
            return;
        }

        if (!$entity->getId() || !$entity->isAttributeChanged('taxi')) {
            return;
        }

        $opportunities = $this->entityManager
            ->getRDBRepository('Opportunity')
            ->where(['appuntamentoId' => $entity->getId()])
            ->find();

        foreach ($opportunities as $opportunity) {
            $quotes = $this->entityManager
                ->getRDBRepository('Quote')
                ->where(['opportunityId' => $opportunity->getId()])
                ->find();

            foreach ($quotes as $quote) {
                try {
                    $this->provvigioneManager->recalculateAllForQuote($quote);
                } catch (\Throwable $e) {
                    error_log(
                        'RecalculateProvvigioniOnTaxiChange ['
                        . $entity->getId()
                        . ' / '
                        . $quote->getId()
                        . ']: '
                        . $e->getMessage()
                    );
                }
            }
        }
    }
}
