<?php

namespace Espo\Custom\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Controllers\Record;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\InjectableFactory;
use Espo\Custom\Services\AppuntamentoRifissatoCreator;
use Espo\Custom\Services\CrmKpi\CrmKpiService;
use Espo\Custom\Tools\CrmKpi\DateRange;

/**
 * Appuntamento custom actions: KPI dashlet + rifissato.
 *
 * GET  api/v1/Appuntamento/action/getSummary
 * GET  api/v1/Appuntamento/action/crmKpiSummary
 * POST api/v1/Appuntamento/action/createRifissato
 */
class Appuntamento extends Record
{
    public function getActionGetSummary(Request $request, Response $response): object
    {
        return $this->buildSummary($request);
    }

    public function getActionCrmKpiSummary(Request $request, Response $response): object
    {
        return $this->buildSummary($request);
    }

    /**
     * @return array{id: string}
     */
    public function postActionCreateRifissato(Request $request): array
    {
        $data = $request->getParsedBody();
        $sourceId = is_object($data) ? ($data->sourceId ?? null) : null;
        $dateStart = is_object($data) ? ($data->dateStart ?? null) : null;
        $assignedUsersIds = is_object($data) ? ($data->assignedUsersIds ?? []) : [];

        if (!is_array($assignedUsersIds)) {
            $assignedUsersIds = [];
        }

        $creator = new AppuntamentoRifissatoCreator(
            $this->getContainer()->get('entityManager')
        );

        $id = $creator->create(
            (string) $sourceId,
            (string) $dateStart,
            $assignedUsersIds,
        );

        return ['id' => $id];
    }

    private function buildSummary(Request $request): object
    {
        $period = DateRange::normalizePeriod($request->getQueryParam('period') ?? DateRange::CURRENT_MONTH);

        $productBrandId = $this->normalizeBrandId($request->getQueryParam('productBrandId'));

        /** @var InjectableFactory $injectableFactory */
        $injectableFactory = $this->getContainer()->get('injectableFactory');
        $service = $injectableFactory->create(CrmKpiService::class);
        $user = $this->getUser();

        if (!$user) {
            throw new Forbidden();
        }

        return $service->getSummary($user, $period, $productBrandId);
    }

    private function normalizeBrandId(?string $productBrandId): ?string
    {
        $productBrandId = trim((string) $productBrandId);

        return $productBrandId !== '' ? $productBrandId : null;
    }
}
