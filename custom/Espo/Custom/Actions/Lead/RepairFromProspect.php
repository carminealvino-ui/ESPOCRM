<?php

namespace Espo\Custom\Actions\Lead;

use Espo\Custom\Services\LeadProspectSync;
use Espo\ORM\EntityManager;

/**
 * Ripara Lead esistenti copiando dati dal Prospect collegato.
 */
class RepairFromProspect
{
    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @param array{onlyEmpty?:bool, limit?:int|null} $params
     */
    public function run(array $params = []): object
    {
        $onlyEmpty = $params['onlyEmpty'] ?? true;
        $limit = $params['limit'] ?? null;
        $leadId = $params['leadId'] ?? null;

        $sync = new LeadProspectSync($this->entityManager);

        if ($leadId) {
            return (object) $sync->repairOneLead($leadId, $onlyEmpty);
        }

        $stats = $sync->repairAllLeads($onlyEmpty, $limit);

        return (object) $stats;
    }
}
