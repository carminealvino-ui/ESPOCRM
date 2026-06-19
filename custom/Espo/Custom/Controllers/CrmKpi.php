<?php

namespace Espo\Custom\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\InjectableFactory;
use Espo\Custom\Services\CrmKpi\CrmKpiService;

class CrmKpi extends \Espo\Core\Templates\Controllers\Base
{
    public function getActionSummary(Request $request, Response $response): object
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
