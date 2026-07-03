<?php

namespace Espo\Custom\Hooks\Call;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Data Riscontro: vuota in Pianificato. Non valorizzare automaticamente a Svolto/Non svolto.
 */
class SetDataRiscontroOnComplete implements BeforeSave
{
    public static int $order = 6;

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks') || $options->get('isImport')) {
            return;
        }

        if ($entity->getEntityType() !== 'Call') {
            return;
        }

        if ((string) $entity->get('status') !== 'Planned') {
            return;
        }

        if ($entity->get('data') !== null && $entity->get('data') !== '') {
            $entity->set('data', null);
        }

        if ($entity->hasAttribute('dataRiscontro')
            && $entity->get('dataRiscontro') !== null
            && $entity->get('dataRiscontro') !== '') {
            $entity->set('dataRiscontro', null);
        }
    }
}
