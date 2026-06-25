<?php

namespace Espo\Custom\Hooks\Call;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Custom\Tools\DateTime\BusinessDateTime;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Data Riscontro: vuota in Pianificato, valorizzata al passaggio a Svolto/Non svolto.
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

        $status = (string) $entity->get('status');

        if ($status === 'Planned') {
            if ($entity->get('data') !== null && $entity->get('data') !== '') {
                $entity->set('data', null);
            }

            return;
        }

        if (!in_array($status, ['Held', 'Not Held'], true)) {
            return;
        }

        if ($entity->get('data')) {
            return;
        }

        $today = (new \DateTimeImmutable('now', new \DateTimeZone(BusinessDateTime::BUSINESS_TIMEZONE)))
            ->format('Y-m-d');

        $entity->set('data', $today);
    }
}
