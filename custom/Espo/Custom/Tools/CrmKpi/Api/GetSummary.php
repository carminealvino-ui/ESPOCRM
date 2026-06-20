<?php

namespace Espo\Custom\Tools\CrmKpi\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\ApplicationUser;
use Espo\Core\InjectableFactory;
use Espo\Custom\Services\CrmKpi\CrmKpiService;
use Espo\Custom\Tools\CrmKpi\DateRange;

class GetSummary implements Action
{
    public function __construct(
        private InjectableFactory $injectableFactory,
        private ApplicationUser $applicationUser,
    ) {}

    public function process(Request $request): Response
    {
        $user = $this->applicationUser->getUser();

        if (!$user) {
            throw new Forbidden();
        }

        $period = DateRange::normalizePeriod($request->getQueryParam('period') ?? DateRange::CURRENT_MONTH);

        $service = $this->injectableFactory->create(CrmKpiService::class);
        $summary = $service->getSummary($user, $period);

        return ResponseComposer::json($summary);
    }
}
