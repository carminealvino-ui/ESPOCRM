<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Numero contratto valorizzato → stato Bozza diventa In lavorazione (schema semplificato)
 * oppure Draft diventa Presented (schema legacy Espo).
 */
class SetPresentedWhenNumeroContratto implements BeforeSave
{
    public static int $order = 12;

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($entity->getEntityType() !== 'Quote') {
            return;
        }

        if ($options->get('silent') || $options->get('skipHooks')) {
            return;
        }

        if (!$this->hasNumeroContratto($entity)) {
            return;
        }

        $status = $entity->get('status');
        $nextStatus = match ($status) {
            'Bozza' => 'In lavorazione',
            'Draft' => 'Presented',
            default => null,
        };

        if ($nextStatus === null) {
            return;
        }

        $entity->set('status', $nextStatus);
    }

    private function hasNumeroContratto(Entity $entity): bool
    {
        foreach (['numeroContratto', 'number'] as $field) {
            $value = $entity->get($field);

            if ($value !== null && trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }
}
