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

namespace Espo\Modules\Sales\Classes\FieldSavers\PaymentChannel;

use Espo\Core\FieldProcessing\Saver;
use Espo\Core\FieldProcessing\Saver\Params;
use Espo\Core\ORM\Type\FieldType;
use Espo\Core\Utils\Crypt;
use Espo\Modules\Sales\Entities\PaymentChannel;
use Espo\Modules\Sales\Tools\PaymentChannel\RecordProvider;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use stdClass;

/**
 * @implements Saver<PaymentChannel>
 */
class DataSaver implements Saver
{
    public function __construct(
        private RecordProvider $recordProvider,
        private EntityManager $entityManager,
        private Crypt $crypt,
    ) {}

    public function process(Entity $entity, Params $params): void
    {
        $data = $entity->getData();

        if ($data === null && !$entity->isNew()) {
            return;
        }

        $record = $this->recordProvider->get($entity);

        $this->prepareData($record, $data);

        if ($data) {
            $record->setMultiple($data);
        }

        $this->entityManager->saveEntity($record);
    }

    private function prepareData(Entity $record, stdClass $data): void
    {
        $fieldDefsList = $this->entityManager
            ->getDefs()
            ->getEntity($record->getEntityType())
            ->getFieldList();

        foreach ($fieldDefsList as $fieldDefs) {
            $field = $fieldDefs->getName();

            if (
                $fieldDefs->getType() === FieldType::PASSWORD &&
                property_exists($data, $field)
            ) {
                $value = $data->$field;

                if (is_string($value)) {
                    $data->$field = $this->crypt->encrypt($value);
                }
            }
        }
    }
}
