<?php

namespace Espo\Custom\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Controllers\Record;
use Espo\Custom\Actions\Lead\RepairFromProspect;

class Lead extends Record
{
    /**
     * POST Lead/action/repairFromProspect
     * Body opzionale: { "onlyEmpty": true, "limit": 500 }
     */
    public function postActionRepairFromProspect(
        Request $request,
        Response $response
    ) {
        $data = $request->getParsedBody();

        $params = [
            'onlyEmpty' => $data->onlyEmpty ?? true,
            'limit' => isset($data->limit) ? (int) $data->limit : null,
        ];

        $entityManager = $this->getContainer()->get('entityManager');

        $action = new RepairFromProspect($entityManager);

        return $action->run($params);
    }
}
