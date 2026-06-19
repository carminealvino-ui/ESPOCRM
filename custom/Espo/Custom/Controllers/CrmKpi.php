<?php

namespace Espo\Custom\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\ApplicationUser;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\InjectableFactory;
use Espo\Custom\Services\CrmKpi\CrmKpiService;

/**
 * Controller API virtuale (non estende Record: CrmKpi non è un'entità).
 * Endpoint: GET api/v1/CrmKpi/action/summary
 */
class CrmKpi
{
    public function __construct(
        private InjectableFactory $injectableFactory,
        private ApplicationUser $applicationUser,
    ) {}

    public function getActionSummary(Request $request, Response $response): object
    {
        $user = $this->applicationUser->getUser();

        if (!$user) {
            throw new Forbidden();
        }

        $period = $request->getQueryParam('period') ?? 'currentMonth';

        if (!in_array($period, ['currentMonth', 'previousMonth'], true)) {
            $period = 'currentMonth';
        }

        $service = $this->injectableFactory->create(CrmKpiService::class);

        return $service->getSummary($user, $period);
    }
}
