<?php

namespace Espo\Custom\Tools\CrmKpi\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\InjectableFactory;
use Espo\Custom\Services\CrmKpi\CrmKpiService;
use Espo\Entities\User;

/**
 * Fallback API esplicita: GET api/v1/CrmKpi/action/summary
 */
class GetSummary implements Action
{
    public function __construct(
        private InjectableFactory $injectableFactory,
        private User $user,
    ) {}

    public function process(Request $request): object
    {
        $period = $request->getQueryParam('period') ?? 'currentMonth';

        if (!in_array($period, ['currentMonth', 'previousMonth'], true)) {
            $period = 'currentMonth';
        }

        $service = $this->injectableFactory->create(CrmKpiService::class);

        return $service->getSummary($this->user, $period);
    }
}
