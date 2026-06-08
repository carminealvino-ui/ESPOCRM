<?php

namespace Espo\Custom\Classes\FieldValidators\ProductPrice\Price;

use Espo\Core\FieldValidation\Validator;
use Espo\Core\FieldValidation\Validator\Data;
use Espo\Core\FieldValidation\Validator\Failure;
use Espo\ORM\Entity;

/**
 * Accetta price oppure almeno un prezzo listino dual-IVA (IVI o netto).
 *
 * @implements Validator<Entity>
 */
class RequiredOrDualIva implements Validator
{
    public function validate(Entity $entity, string $field, Data $data): ?Failure
    {
        if ($this->isPositive($entity->get('price'))) {
            return null;
        }

        if (
            $this->isPositive($entity->get('prezzoListinoIvaInclusa')) ||
            $this->isPositive($entity->get('prezzoListinoIvaEsclusa'))
        ) {
            return null;
        }

        return Failure::create();
    }

    private function isPositive(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        if (!is_numeric($value)) {
            return false;
        }

        return (float) $value > 0;
    }
}
