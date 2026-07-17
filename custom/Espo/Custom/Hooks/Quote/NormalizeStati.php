<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Mantiene coerenti status/statoContratto in fase di salvataggio.
 */
class NormalizeStati implements BeforeSave
{
    public static int $order = 11;

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('silent') || $options->get('skipHooks')) {
            return;
        }

        $status = (string) ($entity->get('status') ?? '');
        $statoContratto = (string) ($entity->get('statoContratto') ?? '');

        if ($statoContratto === '') {
            $statoContratto = 'Inserito';
        }

        if ($status === 'Appuntamento fissato') {
            $statoContratto = 'In pagamento';
        } elseif ($status === 'Installato' && in_array($statoContratto, ['', 'Inserito', 'In pagamento'], true)) {
            $statoContratto = 'Chiuso';
        } elseif ($status === 'Invalido' && in_array($statoContratto, ['', 'Inserito', 'In pagamento'], true)) {
            $statoContratto = 'Annullato';
        }

        $entity->set('statoContratto', $statoContratto);
    }
}
