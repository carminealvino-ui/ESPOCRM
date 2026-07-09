<?php

namespace Espo\Custom\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\ApplicationUser;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\InjectableFactory;
use Espo\Custom\Services\CrmKpi\CrmKpiService;
use Espo\Custom\Tools\CrmKpi\DateRange;

/**
 * Alias retrocompatibilità (CrmKpi/action/getSummary).
 */
class CrmKpi
{
    public function __construct(
        private InjectableFactory $injectableFactory,
        private ApplicationUser $applicationUser,
    ) {}

    public function getActionGetSummary(Request $request, Response $response): object
    {
        return $this->buildSummary($request);
    }

    public function getActionSummary(Request $request, Response $response): object
    {
        return $this->buildSummary($request);
    }

    public function getActionCrmKpiSummary(Request $request, Response $response): object
    {
        return $this->buildSummary($request);
    }

    private function buildSummary(Request $request): object
    {
        $user = $this->applicationUser->getUser();

        if (!$user) {
            throw new Forbidden();
        }

        $period = DateRange::normalizePeriod($request->getQueryParam('period') ?? DateRange::CURRENT_MONTH);
        $productBrandId = trim((string) ($request->getQueryParam('productBrandId') ?? ''));
        $productBrandId = $productBrandId !== '' ? $productBrandId : null;

        $service = $this->injectableFactory->create(CrmKpiService::class);

        return $service->getSummary($user, $period, $productBrandId);
    }
}
