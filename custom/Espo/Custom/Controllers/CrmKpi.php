<?php

namespace Espo\Custom\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\ApplicationUser;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\InjectableFactory;
use Espo\Custom\Services\CrmKpi\CrmKpiService;
use Espo\Custom\Tools\CrmKpi\Period;

/**
 * Alias retrocompatibilità se il browser ha ancora JS in cache (CrmKpi/action/getSummary).
 * Preferire Appuntamento/action/getSummary.
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

        $period = Period::normalize($request->getQueryParam('period') ?? Period::CURRENT_MONTH);

        $service = $this->injectableFactory->create(CrmKpiService::class);

        return $service->getSummary($user, $period);
    }
}
