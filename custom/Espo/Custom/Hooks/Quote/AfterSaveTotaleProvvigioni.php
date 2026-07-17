<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Custom\Services\ProvvigioneManager;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Provvigioni Totali = somma importoConsolidato (o importo) delle Provvigioni del contratto.
 * Esclude Inesigibile. Non usa la formula legacy 15%+35% sull'imponibile.
 */
class AfterSaveTotaleProvvigioni implements AfterSave
{
    public static int $order = 20;

    public function __construct(
        private ProvvigioneManager $provvigioneManager
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks') || $options->get('silent')) {
            return;
        }

        if (!$entity->getId()) {
            return;
        }

        $this->provvigioneManager->refreshQuoteTotaleProvvigioni($entity);
    }
}
