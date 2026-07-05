<?php

namespace Espo\Custom\Hooks\Appuntamento;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Normalizza campi obbligatori / tipi prima del persist (form calendario e modale).
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

    /** @var string[] */
    private const MULTI_ENUM_FIELDS = [
        'tipo',
    ];

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks')) {
            return;
        }

        foreach (self::BOOL_DEFAULTS as $field => $default) {
            $this->normalizeBool($entity, $field, $default);
        }

        foreach (self::MULTI_ENUM_FIELDS as $field) {
            $this->normalizeMultiEnum($entity, $field);
        }
    }

    private function normalizeBool(Entity $entity, string $field, bool $default): void
    {
        if (!$entity->hasAttribute($field)) {
            return;
        }

        $value = $entity->get($field);

        if ($value === null || $value === '') {
            $entity->set($field, $default);

            return;
        }

        if (is_string($value)) {
            $lower = strtolower($value);

            if ($lower === 'true' || $lower === '1') {
                $entity->set($field, true);

                return;
            }

            if ($lower === 'false' || $lower === '0') {
                $entity->set($field, false);
            }
        }
    }

    private function normalizeMultiEnum(Entity $entity, string $field): void
    {
        if (!$entity->hasAttribute($field)) {
            return;
        }

        $value = $entity->get($field);

        if ($value === null || $value === '' || $value === []) {
            return;
        }

        if (is_string($value)) {
            $entity->set($field, [$value]);
        }
    }
}
