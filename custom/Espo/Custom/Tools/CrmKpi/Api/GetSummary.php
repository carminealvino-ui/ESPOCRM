<?php

namespace Espo\Custom\Tools\CrmKpi\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Error\Body;
use Espo\Core\Exceptions\Error;
use Espo\Core\InjectableFactory;
use Espo\Custom\Services\CrmKpi\CrmKpiService;
use Espo\Entities\User;

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

        try {
            $service = $this->injectableFactory->create(CrmKpiService::class);

            return $service->getSummary($this->user, $period);
        } catch (\Throwable $e) {
            throw new Error(
                Body::create()
                    ->withMessageTranslation('KPI CRM: ' . $e->getMessage(), null, ['scope' => 'Global'])
                    ->encode(),
                500,
                $e
            );
        }
    }
}
