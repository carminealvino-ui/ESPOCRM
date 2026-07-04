<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Custom\Services\ProvvigioneManager;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Sovrascrive totaleProvvigioni dopo la formula (ordine 11) con la somma degli importi consolidati.
 */
class BeforeSaveTotaleProvvigioni implements BeforeSave
{
    public static int $order = 15;

    public function __construct(
        private ProvvigioneManager $provvigioneManager
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks') || $options->get('silent')) {
            return;
        }

        if (!$entity->getId()) {
            return;
        }

        $totale = $this->provvigioneManager->resolveTotaleProvvigioniForQuoteId($entity->getId());

        $entity->set('totaleProvvigioni', $totale);
    }
}
