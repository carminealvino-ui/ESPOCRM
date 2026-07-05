<?php

namespace Espo\Custom\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Controllers\Record;
use Espo\Custom\Services\AppuntamentoRifissatoCreator;
use Espo\ORM\EntityManager;

/**
 * Appuntamento: CRUD standard (Record) + createRifissato.
 * KPI: scope CrmKpi (controller dedicato).
 *
 * POST api/v1/Appuntamento/action/createRifissato
 */
class Appuntamento extends Record
{
    /**
     * @return array{id: string}
     */
    public function postActionCreateRifissato(Request $request): array
    {
        $data = $request->getParsedBody();
        $sourceId = is_object($data) ? ($data->sourceId ?? null) : null;
        $dateStart = is_object($data) ? ($data->dateStart ?? null) : null;
        $assignedUsersIds = is_object($data) ? ($data->assignedUsersIds ?? []) : [];

        if (!is_array($assignedUsersIds)) {
            $assignedUsersIds = [];
        }

        $entityManager = $this->injectableFactory->create(EntityManager::class);
        $creator = new AppuntamentoRifissatoCreator($entityManager);

        $id = $creator->create(
            (string) $sourceId,
            (string) $dateStart,
            $assignedUsersIds,
        );

        return ['id' => $id];
    }
}
