<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Numero contratto valorizzato → Bozza diventa In Gestione.
 */
class SetPresentedWhenNumeroContratto implements BeforeSave
{
    public static int $order = 10;

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
            'Bozza', 'Draft' => 'In Gestione',
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
