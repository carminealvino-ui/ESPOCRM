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
 * License ID: 11af5a568c1a72dce4e164257d1a0207
 ************************************************************************************/

namespace Espo\Modules\Sales\Classes\Record\Order;

use Espo\Core\Record\Input\Data;
use Espo\Core\Record\Input\Filter;
use Espo\Core\Utils\FieldUtil;
use Espo\Core\Utils\Metadata;
use Espo\Core\Utils\ObjectUtil;
use Espo\Modules\Sales\Tools\Sales\OrderEntity;
use Espo\Modules\Sales\Tools\Sales\OrderEntityUtil;
use Espo\ORM\Defs;
use Espo\ORM\Defs\Params\FieldParam;
use stdClass;

/**
 * Note: readOnlyAfterCreate is not supported.
 *
 * @noinspection PhpUnused
 */
class InputFilter implements Filter
{
    private const PARAM_ITEM_NOT_READ_ONLY = 'itemNotReadOnly';

    protected string $itemListAttribute = OrderEntity::ATTR_ITEM_LIST;
    protected ?string $itemEntityType = null;

    public function __construct(
        private Defs $defs,
        private FieldUtil $fieldUtil,
        private Metadata $metadata,
        private ?string $entityType = null,
    ) {}

    public function filter(Data $data): void
    {
        if (!$this->entityType) {
            // Before v9.3.
            return;
        }

        $itemList = $data->get($this->itemListAttribute);

        if (!is_array($itemList)) {
            return;
        }

        $itemEntityType = $this->itemEntityType ?? OrderEntityUtil::getItemEntityType($this->entityType);

        $fields = $this->getReadOnlyFields($itemEntityType);

        foreach ($itemList as $i => $item) {
            if (!$item instanceof stdClass) {
                return;
            }

            $item = ObjectUtil::clone($item);

            $this->filterItem($item, $itemEntityType, $fields);

            $itemList[$i] = $item;
        }

        $data->set($this->itemListAttribute, $itemList);
    }

    /**
     * @param string[] $fields
     */
    private function filterItem(stdClass $item, string $itemEntityType, array $fields): void
    {
        foreach ($fields as $field) {
            $this->filterField($item, $itemEntityType, $field);
        }
    }

    private function filterField(stdClass $item, string $itemEntityType, string $name): void
    {
        foreach ($this->fieldUtil->getAttributeList($itemEntityType, $name) as $attribute) {
            unset($item->$attribute);
        }
    }

    /**
     * @return string[]
     */
    private function getReadOnlyFields(string $itemEntityType): array
    {
        $fields = $this->defs
            ->getEntity($itemEntityType)
            ->getFieldList();

        $ignoreList = [];

        foreach ($fields as $defs) {
            if ($defs->getParam(self::PARAM_ITEM_NOT_READ_ONLY)) {
                array_push($ignoreList, ...$this->getSubFields($defs));
            }
        }

        $readOnlyFields = array_filter($fields, function (Defs\FieldDefs $defs) use ($ignoreList) {
            if (in_array($defs->getName(), $ignoreList)) {
                return false;
            }

            return $defs->getParam(FieldParam::READ_ONLY) && !$defs->getParam(self::PARAM_ITEM_NOT_READ_ONLY);
        });

        $readOnlyFields = array_values($readOnlyFields);

        return array_map(fn ($defs) => $defs->getName(), $readOnlyFields);
    }

    /**
     * @return string[]
     */
    private function getSubFields(Defs\FieldDefs $defs): array
    {
        $typeDefs = $this->metadata->get("fields.{$defs->getType()}");

        if (!is_array($typeDefs)) {
            return [];
        }

        /** @var string[] $subNames */
        $subNames = array_keys($typeDefs['fields'] ?? []);

        if (!$subNames) {
            return [];
        }

        $isPrefix = ($typeDefs['naming'] ?? null) === 'prefix';

        $list = [];

        foreach ($subNames as $subName) {
            if ($isPrefix) {
                $list[] = $subName . ucfirst($defs->getName());
            } else {
                $list[] = $defs->getName() . ucfirst($subName);
            }
        }

        return $list;
    }
}
