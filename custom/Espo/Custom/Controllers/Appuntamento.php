<?php

namespace Espo\Custom\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\InjectableFactory;
use Espo\Core\Templates\Controllers\Base;
use Espo\Custom\Services\CrmKpi\CrmKpiService;
use Espo\Custom\Tools\CrmKpi\Period;

/**
 * KPI dashlet — endpoint su scope Appuntamento (ACL già attivo).
 *
 * GET api/v1/Appuntamento/action/getSummary
 * GET api/v1/Appuntamento/action/crmKpiSummary  (alias)
 */
class Appuntamento extends Base
{
    public function getActionGetSummary(Request $request, Response $response): object
    {
        return $this->buildSummary($request);
    }

    public function getActionCrmKpiSummary(Request $request, Response $response): object
    {
        return $this->buildSummary($request);
    }

    private function buildSummary(Request $request): object
    {
        $period = Period::normalize($request->getQueryParam('period') ?? Period::CURRENT_MONTH);

        /** @var InjectableFactory $injectableFactory */
        $injectableFactory = $this->getContainer()->get('injectableFactory');
        $service = $injectableFactory->create(CrmKpiService::class);

        return $service->getSummary($this->getUser(), $period);
    }
}
