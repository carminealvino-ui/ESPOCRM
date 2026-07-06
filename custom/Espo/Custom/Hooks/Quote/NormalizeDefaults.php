<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Valori di default su campi bool custom per evitare errori di salvataggio.
 */
class NormalizeDefaults implements BeforeSave
{
    public static int $order = 5;

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($entity->getEntityType() !== 'Quote') {
            return;
        }

        foreach ([
            'finanziamento' => false,
            'tassoZero' => false,
            'venditaAbbinataAdAltroProdotto' => false,
        ] as $field => $default) {
            if ($entity->get($field) === null) {
                $entity->set($field, $default);
            }
        }

        if ($entity->get('statoContratto') === null || $entity->get('statoContratto') === '') {
            $entity->set('statoContratto', 'Inserito');
        }
    }
}
