<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Numero contratto valorizzato → stato Bozza diventa In lavorazione.
 * Il Codice Contratto automatico (numberA) è gestito da AssignNumberACodiceContratto.
 */
class SetPresentedWhenNumeroContratto implements BeforeSave
{
    public static int $order = 12;

    private const STATUS_BOZZA = 'Bozza';
    private const STATUS_IN_LAVORAZIONE = 'In lavorazione';

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

        if ($entity->get('status') !== self::STATUS_BOZZA) {
            return;
        }

        $entity->set('status', self::STATUS_IN_LAVORAZIONE);
    }

    private function hasNumeroContratto(Entity $entity): bool
    {
        $value = $entity->get('numeroContratto');

        if ($value === null) {
            return false;
        }

        return trim((string) $value) !== '';
    }
}
