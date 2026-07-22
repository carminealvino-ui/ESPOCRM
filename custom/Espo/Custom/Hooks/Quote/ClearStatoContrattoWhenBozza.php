<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Se Stato = Bozza/Draft → Stato Contratto vuoto (nessuna selezione).
 * Altrimenti, se Stato Contratto è vuoto → default Inserito.
 *
 * Ordine dopo SetPresentedWhenNumeroContratto (status già definitivo).
 */
class ClearStatoContrattoWhenBozza implements BeforeSave
{
    public static int $order = 14;

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($entity->getEntityType() !== 'Quote') {
            return;
        }

        $status = (string) ($entity->get('status') ?? '');

        if ($status === 'Bozza' || $status === 'Draft') {
            $entity->set('statoContratto', null);

            return;
        }

        if ($entity->get('statoContratto') === null || $entity->get('statoContratto') === '') {
            $entity->set('statoContratto', 'Inserito');
        }
    }
}
