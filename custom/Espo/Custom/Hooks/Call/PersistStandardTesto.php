<?php

namespace Espo\Custom\Hooks\Call;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Custom\Services\CallStandardTesto;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

class PersistStandardTesto implements AfterSave
{
    public static int $order = 30;

    public function __construct(
        private CallStandardTesto $standardTesto
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks') || $options->get('isImport')) {
            return;
        }

        if (!$entity->isAttributeChanged('testo')) {
            return;
        }

        $this->standardTesto->persistIfChanged($entity->get('testo'));
    }
}
