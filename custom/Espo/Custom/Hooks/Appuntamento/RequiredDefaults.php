<?php

namespace Espo\Custom\Hooks\Appuntamento;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Default obbligatori per campi bool NOT NULL assenti dal form calendario (edit-small).
 */
class RequiredDefaults implements BeforeSave
{
    public static int $order = 1;

    /** @var array<string, bool> */
    private const BOOL_DEFAULTS = [
        'videoCallTelefonico' => false,
        'zTL' => false,
        'syncConGoogle' => false,
    ];

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks')) {
            return;
        }

        foreach (self::BOOL_DEFAULTS as $field => $default) {
            if (!$entity->hasAttribute($field)) {
                continue;
            }

            if ($entity->get($field) === null) {
                $entity->set($field, $default);
            }
        }
    }
}
