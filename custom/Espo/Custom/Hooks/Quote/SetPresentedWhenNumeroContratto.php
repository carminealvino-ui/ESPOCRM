<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Numero contratto valorizzato → stato Bozza (Draft) diventa Presentato (Presented).
 *
 * @implements BeforeSave<Entity>
 */
class SetPresentedWhenNumeroContratto implements BeforeSave
{
    public static int $order = 12;

    private const STATUS_DRAFT = 'Draft';
    private const STATUS_PRESENTED = 'Presented';

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($entity->getEntityType() !== 'Quote') {
            return;
        }

        if ($options->has('silent') || $options->has('skipHooks')) {
            return;
        }

        if (!$this->hasNumeroContratto($entity)) {
            return;
        }

        if ($entity->get('status') !== self::STATUS_DRAFT) {
            return;
        }

        $entity->set('status', self::STATUS_PRESENTED);
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
