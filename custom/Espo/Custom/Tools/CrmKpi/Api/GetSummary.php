<?php

namespace Espo\Custom\Tools\CrmKpi\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\InjectableFactory;
use Espo\Custom\Services\CrmKpi\CrmKpiService;
use Espo\Custom\Tools\CrmKpi\DateRange;
use Espo\Entities\User;

class GetSummary implements Action
{
    public function __construct(
        private InjectableFactory $injectableFactory,
        private User $user,
    ) {}

    public function process(Request $request): Response
    {
        if (!$this->user->getId()) {
            throw new Forbidden();
        }

        $period = DateRange::normalizePeriod($request->getQueryParam('period') ?? DateRange::CURRENT_MONTH);
        $productBrandId = trim((string) ($request->getQueryParam('productBrandId') ?? ''));
        $productBrandId = $productBrandId !== '' ? $productBrandId : null;

        $service = $this->injectableFactory->create(CrmKpiService::class);
        $summary = $service->getSummary($this->user, $period, $productBrandId);

        return ResponseComposer::json($summary);
    }
}
