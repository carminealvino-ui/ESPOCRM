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

namespace Espo\Modules\Advanced\Tools\Report\Export;

use Espo\Core\AclManager;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Field\LinkParent;
use Espo\Core\InjectableFactory;
use Espo\Core\Select\Where\Item as WhereItem;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Metadata;
use Espo\Entities\Attachment;
use Espo\Entities\Template;
use Espo\Entities\User;
use Espo\Modules\Advanced\Entities\Report;
use Espo\Modules\Advanced\Tools\Report\GridType\Data as GridTypeData;
use Espo\Modules\Advanced\Tools\Report\GridType\Helper as GridHelper;
use Espo\Modules\Advanced\Tools\Report\GridType\Result as GridResult;
use Espo\Modules\Advanced\Tools\Report\GridType\Util as GridUtil;
use Espo\Modules\Advanced\Tools\Report\Service;
use Espo\Modules\Crm\Entities\Opportunity;
use Espo\ORM\EntityManager;
use Espo\Tools\Pdf\Data;
use Espo\Tools\Pdf\Service as PdfService;
use PhpOffice\PhpSpreadsheet\Exception as PhpSpreadsheetException;
use RuntimeException;

class GridExportService
{
    private const STUB_KEY = '__STUB__';

    public function __construct(
        private EntityManager $entityManager,
        private AclManager $aclManager,
        private Metadata $metadata,
        private Config $config,
        private Language $language,
        private Service $service,
        private GridHelper $gridHelper,
        private GridUtil $gridUtil,
        private InjectableFactory $injectableFactory,
        private PdfService $pdfService,
        private Grid2NormalizedDataBuilder $grid2NormalizedDataBuilder,
        private Grid1DataBuilder $grid1DataBuilder,
        private CellValueHelper $cellValueHelper,
    ) {}

    /**
     * @throws Forbidden
     * @throws NotFound
     * @throws Error
     * @throws BadRequest
     */
    public function exportXlsx(string $id, ?WhereItem $where, ?User $user = null): string
    {
        $report = $this->entityManager->getRDBRepositoryByClass(Report::class)->getById($id);

        if (!$report) {
            throw new NotFound();
        }

        $this->checkAccess($report, $user);

        $contents = $this->buildXlsxContents($id, $where, $user);

        $mimeType = $this->metadata->get(['app', 'export', 'formatDefs', 'xlsx', 'mimeType']);

        $fileName = $this->prepareXlsxFileName($report);

        $attachment = $this->entityManager->getRDBRepositoryByClass(Attachment::class)->getNew();

        $attachment
            ->setName($fileName)
            ->setRole(Attachment::ROLE_EXPORT_FILE)
            ->setType($mimeType)
            ->setContents($contents)
            ->setRelated(LinkParent::createFromEntity($report));

        $this->entityManager->saveEntity($attachment);

        return $attachment->getId();
    }

    /**
     * @throws Error
     * @throws Forbidden
     * @throws NotFound
     * @throws BadRequest
     */
    public function buildXlsxContents(string $id, ?WhereItem $where, ?User $user = null): string
    {
        $report = $this->entityManager->getRDBRepositoryByClass(Report::class)->getById($id);

        if (!$report) {
            throw new NotFound();
        }

        $entityType = $report->getTargetEntityType();

        $groupCount = count($report->getGroupBy());

        $columnList = $report->getColumns();
        $groupByList = $report->getGroupBy();

        $reportResult = null;

        if (
            $report->getType() === Report::TYPE_JOINT_GRID ||
            !$report->getGroupBy()
        ) {
            $reportResult = $this->service->runGrid($id, $where, $user);

            $columnList = $reportResult->getColumnList();
            $groupByList = $reportResult->getGroupByList();
            $groupCount = count($groupByList);
        }

        $reportResult ??= $this->service->runGrid($id, $where, $user);

        $result = [];
        $sheetData = null;

        if ($groupCount === 2 && $reportResult->getTableMode() !== GridTypeData::TABLE_MODE_NORMALIZED) {
            foreach ($reportResult->getSummaryColumnList() as $column) {
                $result[] = $this->getGrid2ResultForExport($reportResult, $column);
            }
        } else if ($groupCount === 2 && $reportResult->getTableMode() === GridTypeData::TABLE_MODE_NORMALIZED) {
            $sheetData = $this->grid2NormalizedDataBuilder->build($reportResult);
        } else if ($groupCount === 1 && $reportResult->getSubListColumnList()) {
            $sheetData = $this->grid1DataBuilder->build($reportResult);
        } else {
            $result[] = $this->getGrid1ResultForExport($reportResult);
        }

        $columnTypes = [];

        foreach ($columnList as $item) {
            $columnData = $this->gridHelper->getDataFromColumnName($entityType, $item, $reportResult);

            $type = $this->metadata
                ->get(['entityDefs', $columnData->entityType, 'fields', $columnData->field, 'type']);

            if (
                $entityType === Opportunity::ENTITY_TYPE &&
                $columnData->field === 'amountWeightedConverted'
            ) {
                $type = 'currencyConverted';
            }

            if ($columnData->function === 'COUNT') {
                $type = 'int';
            }

            $columnTypes[$item] = $type;
        }

        $columnLabels = [];

        if ($groupCount === 2) {
            $columnNameMap = $reportResult->getColumnNameMap();

            foreach ($columnList as $column) {
                $columnLabels[$column] = $columnNameMap[$column] ?? null;
            }
        }

        $exportParams = [
            'exportName' => $report->getName(),
            'columnList' => $columnList,
            'columnTypes' => $columnTypes,
            'chartType' => $reportResult->getChartType() ?? $report->getChartType(),
            'groupByList' => $groupByList,
            'columnLabels' => $columnLabels,
            'reportResult' => $reportResult,
            'groupLabel' => '',
            'currency' => $reportResult->getCurrency(),
        ];

        if ($groupCount) {
            $group = $groupByList[$groupCount - 1];
            $exportParams['groupLabel'] = $reportResult->getGroupNameMap()[$group] ??
                $this->gridUtil->translateGroupName($entityType, $group);
        }

        $export = $this->injectableFactory->create(ExportXlsx::class);

        if ($sheetData) {
            try {
                return $export->processWithSheedData($reportResult, $sheetData, $report);
            } catch (PhpSpreadsheetException $e) {
                throw new RuntimeException($e->getMessage(), previous: $e);
            }
        }

        try {
            return $export->process($entityType, $exportParams, $result);
        } catch (PhpSpreadsheetException $e) {
            throw new RuntimeException($e->getMessage(), previous: $e);
        }
    }

    /**
     * @throws Forbidden
     * @throws NotFound
     * @throws Error
     * @throws BadRequest
     */
    public function exportCsv(
        string $id,
        ?WhereItem $where,
        ?string $column = null,
        ?User $user = null,
    ): string  {

        $report = $this->entityManager->getRDBRepositoryByClass(Report::class)->getById($id);

        if (!$report) {
            throw new NotFound();
        }

        $this->checkAccess($report, $user);

        $contents = $this->getGridReportCsv($id, $where, $column, $user);

        $mimeType = $this->metadata->get(['app', 'export', 'formatDefs', 'csv', 'mimeType']);

        $fileName = $this->prepareCsvFileName($report);

        $attachment = $this->entityManager->getRDBRepositoryByClass(Attachment::class)->getNew();

        $attachment
            ->setName($fileName)
            ->setRole(Attachment::ROLE_EXPORT_FILE)
            ->setType($mimeType)
            ->setContents($contents)
            ->setRelated(LinkParent::createFromEntity($report));

        $this->entityManager->saveEntity($attachment);

        return $attachment->getId();
    }

    /**
     * @throws Forbidden
     * @throws Error
     * @throws NotFound
     * @throws BadRequest
     */
    private function getGridReportCsv(
        string $id,
        ?WhereItem $where,
        ?string $column = null,
        ?User $user = null,
    ): string {

        $result = $this->getGridReportResultForExport(
            id: $id,
            where: $where,
            currentColumn: $column,
            user: $user,
        );

        $delimiter = $this->config->get('exportDelimiter', ';');

        $fp = fopen('php://temp', 'w');

        if ($fp === false) {
            throw new RuntimeException("Could not open temp.");
        }

        foreach ($result as $row) {
            fputcsv($fp, $row, $delimiter);
        }

        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        if ($csv === false) {
            throw new RuntimeException("Could not get from stream.");
        }

        return $csv;
    }

    /**
     * @return array<int, mixed>[]
     * @throws Error
     * @throws Forbidden
     * @throws NotFound
     * @throws BadRequest
     */
    private function getGridReportResultForExport(
        string $id,
        ?WhereItem $where,
        ?string $currentColumn = null,
        ?User $user = null,
    ): array {

        $reportResult = $this->service->runGrid($id, $where, $user);

        $depth = count($reportResult->getGroupByList());

        if ($depth === 2 && $reportResult->getTableMode() === GridTypeData::TABLE_MODE_NORMALIZED) {
            $sheetData = $this->grid2NormalizedDataBuilder->build($reportResult);

            return $this->sheetDataToRaw($sheetData);
        }

        if ($depth === 1 && $reportResult->getSubListColumnList()) {
            $sheetData = $this->grid1DataBuilder->build($reportResult);

            return $this->sheetDataToRaw($sheetData);
        }

        if ($depth === 2) {
            return $this->getGrid2ResultForExport($reportResult, $currentColumn);
        }

        if ($depth === 1 || $depth === 0) {
            return $this->getGrid1ResultForExport($reportResult);
        }

        throw new RuntimeException();
    }

    public function getCellDisplayValueFromResult(
        int $groupIndex,
        string $groupValue,
        string $column,
        GridResult $reportResult,
    ): mixed {

        return $this->cellValueHelper->getCellDisplayValueFromResult(
            groupIndex: $groupIndex,
            groupValue: $groupValue,
            column: $column,
            reportResult: $reportResult,
        );
    }

    /**
     * @throws Forbidden
     * @throws Error
     * @throws NotFound
     */
    public function exportPdf(
        string $id,
        ?WhereItem $where,
        string $templateId,
        ?User $user = null,
    ): string {

        $report = $this->entityManager->getRDBRepositoryByClass(Report::class)->getById($id);

        $template = $this->entityManager->getRDBRepositoryByClass(Template::class)->getById($templateId);

        if (!$report || !$template) {
            throw new NotFound();
        }

        if ($user) {
            if (!$this->aclManager->checkEntityRead($user, $report)) {
                throw new Forbidden("No access to report.");
            }

            if (!$this->aclManager->checkEntityRead($user, $template)) {
                throw new Forbidden("No access to template.");
            }
        }

        $additionalData = [
            'user' => $user,
            'reportWhere' => $where,
        ];

        $contents = $this->pdfService
            ->generate(
                Report::ENTITY_TYPE,
                $report->getId(),
                $template->getId(),
                null,
                Data::create()->withAdditionalTemplateData((object) $additionalData)
            )
            ->getString();

        $attachment = $this->entityManager->getRDBRepositoryByClass(Attachment::class)->getNew();

        $attachment
            ->setRole(Attachment::ROLE_EXPORT_FILE)
            ->setType('application/pdf')
            ->setContents($contents)
            ->setRelated(LinkParent::createFromEntity($report));

        $this->entityManager->saveEntity($attachment);

        return $attachment->getId();
    }

    /**
     * @return array<int, mixed>[]
     */
    private function getGrid2ResultForExport(GridResult $reportResult, ?string $currentColumn): array
    {
        $result = [];

        $reportData = $reportResult->getReportData();

        $groupName1 = $reportResult->getGroupByList()[0];
        $groupName2 = $reportResult->getGroupByList()[1];

        $group1NonSummaryColumnList = [];
        $group2NonSummaryColumnList = [];

        if ($reportResult->getGroup1NonSummaryColumnList() !== null) {
            $group1NonSummaryColumnList = $reportResult->getGroup1NonSummaryColumnList();
        }

        if ($reportResult->getGroup2NonSummaryColumnList() !== null) {
            $group2NonSummaryColumnList = $reportResult->getGroup2NonSummaryColumnList();
        }

        $row = [];

        $row[] = '';

        foreach ($group2NonSummaryColumnList as $column) {
            $text = $reportResult->getColumnNameMap()[$column];

            $row[] = $text;
        }

        foreach ($reportResult->getGrouping()[0] ?? [] as $gr1) {
            $label = $gr1;

            if (empty($label)) {
                $label = $this->language->translate('-Empty-', 'labels', 'Report');
            } else if (!empty($reportResult->getGroupValueMap()[$groupName1][$gr1])) {
                $label = $reportResult->getGroupValueMap()[$groupName1][$gr1];
            }

            $row[] = $label;
        }

        $result[] = $row;

        foreach ($reportResult->getGrouping()[1] ?? [] as $gr2) {
            $row = [];
            $label = $gr2;

            if (empty($label)) {
                $label = $this->language->translate('-Empty-', 'labels', 'Report');
            } else if (!empty($reportResult->getGroupValueMap()[$groupName2][$gr2])) {
                $label = $reportResult->getGroupValueMap()[$groupName2][$gr2];
            }

            $row[] = $label;

            foreach ($group2NonSummaryColumnList as $column) {
                $row[] = $this->getCellDisplayValueFromResult(1, $gr2, $column, $reportResult);
            }

            foreach ($reportResult->getGrouping()[0] ?? [] as $gr1) {
                $value = 0;

                if (!empty($reportData->$gr1) && !empty($reportData->$gr1->$gr2)) {
                    if (!empty($reportData->$gr1->$gr2->$currentColumn)) {
                        $value = $reportData->$gr1->$gr2->$currentColumn;
                    }
                }

                $row[] = $value;
            }

            $result[] = $row;
        }

        $row = [];

        $row[] = $this->language->translate('Total', 'labels', 'Report');

        foreach ($group2NonSummaryColumnList as $ignored) {
            $row[] = '';
        }

        foreach ($reportResult->getGrouping()[0] ?? [] as $gr1) {
            $sum = 0;

            if (!empty($reportResult->getGroup1Sums()->$gr1)) {
                if (!empty($reportResult->getGroup1Sums()->$gr1->$currentColumn)) {
                    $sum = $reportResult->getGroup1Sums()->$gr1->$currentColumn;
                }
            }

            $row[] = $sum;
        }

        $result[] = $row;

        if (count($group1NonSummaryColumnList)) {
            $result[] = [];
        }

        foreach ($group1NonSummaryColumnList as $column) {
            $row = [];
            $text = $reportResult->getColumnNameMap()[$column];
            $row[] = $text;

            foreach ($group2NonSummaryColumnList as $ignored) {
                $row[] = '';
            }

            foreach ($reportResult->getGrouping()[0] ?? [] as $gr1) {
                $row[] = $this->getCellDisplayValueFromResult(0, $gr1, $column, $reportResult);
            }

            $result[] = $row;
        }
        return $result;
    }

    /**
     * @return array<int, mixed>[]
     */
    private function getGrid1ResultForExport(GridResult $reportResult): array
    {
        $result = [];

        $depth = count($reportResult->getGroupByList());
        $reportData = $reportResult->getReportData();

        $aggregatedColumnList = $reportResult->getAggregatedColumnList();

        if ($depth === 1) {
            $groupName = $reportResult->getGroupByList()[0];
        } else {
            $groupName = self::STUB_KEY;
        }

        $row = [];
        $row[] = '';

        foreach ($aggregatedColumnList as $column) {
            $label = $column;

            if (!empty($reportResult->getColumnNameMap()[$column])) {
                $label = $reportResult->getColumnNameMap()[$column];
            }

            $row[] = $label;
        }

        $result[] = $row;

        foreach ($reportResult->getGrouping()[0] ?? [] as $gr) {
            $row = [];

            $label = $gr;

            if (empty($label)) {
                $label = $this->language->translate('-Empty-', 'labels', 'Report');
            } else if (
                !empty($reportResult->getGroupValueMap()[$groupName]) &&
                array_key_exists($gr, $reportResult->getGroupValueMap()[$groupName])
            ) {
                $label = $reportResult->getGroupValueMap()[$groupName][$gr];
            }

            $row[] = $label;

            foreach ($aggregatedColumnList as $column) {
                if (in_array($column, $reportResult->getNumericColumnList())) {
                    $value = 0;

                    if (!empty($reportData->$gr)) {
                        if (!empty($reportData->$gr->$column)) {
                            $value = $reportData->$gr->$column;
                        }
                    }
                } else {
                    $value = '';

                    if (property_exists($reportData, $gr) && property_exists($reportData->$gr, $column)) {
                        $value = $reportData->$gr->$column;

                        if (
                            !is_null($value) &&
                            property_exists($reportResult->getCellValueMaps(), $column) &&
                            property_exists($reportResult->getCellValueMaps()->$column, $value)
                        ) {
                            $value = $reportResult->getCellValueMaps()->$column->$value;
                        }
                    }
                }

                $row[] = $value;
            }

            $result[] = $row;
        }

        if ($depth) {
            $row = [];

            $row[] = $this->language->translate('Total', 'labels', 'Report');

            foreach ($aggregatedColumnList as $column) {
                if (!in_array($column, $reportResult->getNumericColumnList())) {
                    $row[] = '';

                    continue;
                }

                $sum = 0;

                if (!empty($reportResult->getSums()->$column)) {
                    $sum = $reportResult->getSums()->$column;
                }

                $row[] = $sum;
            }

            $result[] = $row;
        }

        return $result;
    }

    private function prepareXlsxFileName(Report $report): string
    {
        $name = preg_replace("/([^\w\s\d\-_~,;:\[\]().])/u", '_', $report->getName()) . ' ' . date('Y-m-d');

        $fileExtension = $this->metadata->get(['app', 'export', 'formatDefs', 'xlsx', 'fileExtension']);

        return $name . '.' . $fileExtension;
    }

    private function prepareCsvFileName(Report $report): string
    {
        $name = preg_replace("/([^\w\s\d\-_~,;:\[\]().])/u", '_', $report->getName()) . ' ' . date('Y-m-d');

        $fileExtension = $this->metadata->get(['app', 'export', 'formatDefs', 'csv', 'fileExtension']);

        return $name . '.' . $fileExtension;
    }

    /**
     * @throws Forbidden
     */
    private function checkAccess(Report $report, ?User $user): void
    {
        if ($user && !$this->aclManager->checkEntityRead($user, $report)) {
            throw new Forbidden();
        }
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function sheetDataToRaw(Xlsx\SheetData $sheetData): array
    {
        $rows = [];

        foreach ($sheetData->rows as $row) {
            $cells = [];

            foreach ($row->cells as $cell) {
                $cells[] = $cell->value;
            }

            $rows[] = $cells;
        }

        return $rows;
    }
}
