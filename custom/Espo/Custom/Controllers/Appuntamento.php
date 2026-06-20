<?php

namespace Espo\Custom\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\InjectableFactory;
use Espo\Core\Templates\Controllers\Base;
use Espo\Custom\Services\CrmKpi\CrmKpiService;

/**
 * Endpoint KPI dashlet: GET api/v1/Appuntamento/action/crmKpiSummary
 * Usa scope Appuntamento (ACL già configurato) invece del controller virtuale CrmKpi.
 */
class Appuntamento extends Base
{
    public function getActionCrmKpiSummary(Request $request, Response $response): object
    {
        $period = $request->getQueryParam('period') ?? 'currentMonth';

        if (!in_array($period, ['currentMonth', 'previousMonth'], true)) {
            $period = 'currentMonth';
        }

        /** @var InjectableFactory $injectableFactory */
        $injectableFactory = $this->getContainer()->get('injectableFactory');
        $service = $injectableFactory->create(CrmKpiService::class);

        return $service->getSummary($this->getUser(), $period);
    }
}
