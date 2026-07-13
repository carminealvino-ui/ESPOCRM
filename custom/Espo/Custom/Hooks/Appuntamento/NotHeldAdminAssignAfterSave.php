<?php

namespace Espo\Custom\Hooks\Appuntamento;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Custom\Services\AppuntamentoGoogleSync;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Dopo il save: allinea «Utenti assegnati» ad admin su Not Held.
 * In beforeSave non si può toccare assignedUsersIds (fatal Espo 10).
 *
 * @implements AfterSave<Entity>
 */
class NotHeldAdminAssignAfterSave implements AfterSave
{
    public static int $order = 11;

    public function __construct(
        private AppuntamentoGoogleSync $appuntamentoGoogleSync
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks') || $options->get('isImport')) {
            return;
        }

        if ($entity->getEntityType() !== 'Appuntamento') {
            return;
        }

        $this->appuntamentoGoogleSync->persistNotHeldAdminAssignees($entity);
    }
}
