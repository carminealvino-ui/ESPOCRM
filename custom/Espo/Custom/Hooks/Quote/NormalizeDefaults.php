<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Valori di default su campi bool custom per evitare errori di salvataggio.
 *
 * Non forza più statoContratto=Inserito: in Bozza deve restare vuoto
 * (vedi ClearStatoContrattoWhenBozza).
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
            'tassoZero' => false,
            'venditaAbbinataAdAltroProdotto' => false,
        ] as $field => $default) {
            if ($entity->get($field) === null) {
                $entity->set($field, $default);
            }
        }

        if ($entity->get('finanziamento') === null) {
            $entity->set('finanziamento', false);
        }
    }
}
