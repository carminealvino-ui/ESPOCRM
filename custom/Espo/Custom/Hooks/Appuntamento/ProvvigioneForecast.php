<?php

namespace Espo\Custom\Hooks\Appuntamento;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Core\Utils\Log;
use Espo\Custom\Services\ProvvigioneManager;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

class ProvvigioneForecast implements AfterSave
{
    public static int $order = 20;

    public function __construct(
        private ProvvigioneManager $provvigioneManager,
        private Log $log,
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('silent')) {
            return;
        }

        if ($entity->get('status') !== 'Held') {
            return;
        }

        if (!$entity->get('productCategoryId')) {
            return;
        }

        try {
            $this->provvigioneManager->syncPrevistaFromAppuntamento($entity);
        } catch (\Throwable $e) {
            $this->log->error(
                'Appuntamento ProvvigioneForecast failed: ' . $e->getMessage(),
                ['id' => $entity->getId()]
            );
        }
    }
}
