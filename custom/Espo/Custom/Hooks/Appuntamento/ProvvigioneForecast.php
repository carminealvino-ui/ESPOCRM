<?php

namespace Espo\Custom\Hooks\Appuntamento;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Custom\Services\ProvvigioneAccrual;
use Espo\Custom\Services\ProvvigioneForecast;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Genera/aggiorna provvigione Prevista da appuntamento (forecast pipeline).
 */
class ProvvigioneForecastHook implements AfterSave
{
    public static int $order = 20;

    public function __construct(
        private ProvvigioneForecast $forecastService
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('silent')) {
            return;
        }

        if (!$entity->get('productCategoryId')) {
            return;
        }

        $this->forecastService->syncFromAppuntamento($entity);
    }
}
