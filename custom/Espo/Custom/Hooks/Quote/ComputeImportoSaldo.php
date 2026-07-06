<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Importo saldo = importo contratto - caparra (solo senza finanziamento).
 */
class ComputeImportoSaldo implements BeforeSave
{
    public static int $order = 8;

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($entity->getEntityType() !== 'Quote') {
            return;
        }

        if ($options->get('silent') || $options->get('skipHooks')) {
            return;
        }

        if ($entity->get('finanziamento')) {
            return;
        }

        $importoLordo = $entity->get('importoContratto');

        if ($importoLordo === null || $importoLordo === '' || (float) $importoLordo === 0.0) {
            $importoLordo = $entity->get('amount');
        }

        if ($importoLordo === null || $importoLordo === '') {
            return;
        }

        $caparra = $entity->get('importoCaparra');

        if ($caparra === null || $caparra === '') {
            $caparra = 0;
        }

        $saldo = (float) $importoLordo - (float) $caparra;

        if (
            $entity->get('importoSaldo') === null
            || $entity->get('importoSaldo') === ''
        ) {
            $entity->set('importoSaldo', round($saldo, 2));
        }
    }
}
