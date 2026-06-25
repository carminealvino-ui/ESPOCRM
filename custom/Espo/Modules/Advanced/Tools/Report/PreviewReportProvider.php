<?php
/***********************************************************************************
 * The contents of this file are subject to the Extension License Agreement
 * ("Agreement") which can be viewed at
 * https://www.espocrm.com/extension-license-agreement/.
 * By copying, installing downloading, or using this file, You have unconditionally
 * agreed to the terms and conditions of the Agreement, and You may not use this
 * file except in compliance with the Agreement. Under the terms of the Agreement,
 * You shall not license, sublicense, sell, resell, rent, lease, lend, distribute,
 * redistribute, market, publish, commercialize, or otherwise transfer rights or
 * usage to the software or any modified version or derivative work of the software
 * created by or for you.
 *
 * Copyright (C) 2015-2026 EspoCRM, Inc.
 *
 * License ID: 3fed3a66185f29dcda0e14c6ee94bf07
 ************************************************************************************/

namespace Espo\Modules\Advanced\Tools\Report;

use Espo\Core\Acl;
use Espo\Core\Acl\Table as AclTable;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Record\ServiceContainer;
use Espo\Entities\User;
use Espo\Modules\Advanced\Entities\Report;
use Espo\Modules\Advanced\Tools\Report\Internal\InternalReportHelper;
use Espo\ORM\EntityManager;
use stdClass;

class PreviewReportProvider
{
    public function __construct(
        private Service $service,
        private Acl $acl,
        private EntityManager $entityManager,
        private ServiceContainer $serviceContainer,
        private User $user,
        private ReportHelper $reportHelper,
        private InternalReportHelper $internalReportHelper,
        private TargetEntityTypeChecker $targetEntityTypeChecker,
    ) {}

    /**
     * @throws BadRequest
     * @throws Forbidden
     */
    public function get(stdClass $data): Report
    {
        $report = $this->prepareReport($data);

        foreach ($report->getJoinedReportIdList() as $subReportId) {
            $subReport = $this->entityManager->getRDBRepositoryByClass(Report::class)->getById($subReportId);

            if (!$subReport) {
                continue;
            }

            $this->reportHelper->checkReportCanBeRun($subReport);

            if (!$this->acl->checkEntityRead($subReport)) {
                throw new Forbidden("No access to sub-report.");
            }
        }

        $this->reportHelper->checkReportCanBeRun($report);

        $this->accessCheck($report);

        return $report;
    }

    /**
     * @throws Forbidden
     * @throws BadRequest
     */
    private function accessCheck(Report $report): void
    {
        if (
            !$this->user->isAdmin() &&
            ($report->isInternal() || $report->getInternalClassName())
        ) {
            throw Forbidden::createWithBody('onlyAdminCanPreviewInternalReports',
                Body::create()->withMessageTranslation('onlyAdminCanPreviewInternalReports', Report::ENTITY_TYPE)
            );
        }

        if (
            $report->getTargetEntityType() &&
            !$this->acl->checkScope($report->getTargetEntityType(), AclTable::ACTION_READ)
        ) {
            throw new Forbidden("No 'read' access to target entity.");
        }

        if ($report->getTargetEntityType()) {
            $this->targetEntityTypeChecker->check($report->getTargetEntityType());
        }
    }

    /**
     * @throws BadRequest
     */
    private function prepareReport(stdClass $data): Report
    {
        $report = $this->entityManager->getRDBRepositoryByClass(Report::class)->getNew();

        $attributeList = [
            'entityType',
            'type',
            'data',
            'columns',
            'groupBy',
            'orderBy',
            'orderByList',
            'filters',
            'filtersDataList',
            'runtimeFilters',
            'filtersData',
            'columnsData',
            'chartColors',
            'chartDataList',
            'chartOneColumns',
            'chartOneY2Columns',
            'chartType',
            'joinedReports',
            'joinedReportLabel',
            'joinedReportDataList',
            'isInternal',
            'internalClassName',
            'internalParams',
        ];

        foreach (array_keys(get_object_vars($data)) as $attribute) {
            if (!in_array($attribute, $attributeList)) {
                unset($data->$attribute);
            }
        }

        $report->setMultiple($data);

        $report
            ->setApplyAcl()
            ->setName('Unnamed');

        if ($report->getInternalClassName()) {
            $this->internalReportHelper->populateFields($report);
        }

        $this->serviceContainer->getByClass(Report::class)->processValidation($report, $data);

        return $report;
    }
}
