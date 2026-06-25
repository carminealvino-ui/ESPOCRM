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

namespace Espo\Modules\Advanced\Core\Bpmn\Elements;

use Espo\Core\Exceptions\Error;
use Espo\Core\Formula\Exceptions\Error as FormulaError;
use Espo\Modules\Advanced\Entities\BpmnFlowNode;
use Espo\ORM\Entity;

/**
 * @noinspection PhpUnused
 */
class EventStartConditionalEventSubProcess extends EventIntermediateConditionalCatch
{
    /** @var string */
    protected $pendingStatus = BpmnFlowNode::STATUS_STANDBY;

    /**
     * @throws FormulaError
     * @throws Error
     */
    protected function getConditionsTarget(): ?Entity
    {
        return $this->getSpecificTarget($this->getFlowNode()->getDataItemValue('subProcessTarget'));
    }

    /**
     * @inheritDoc
     */
    protected function processNextElement(
        ?string $nextElementId = null,
        $divergentFlowNodeId = false,
        bool $dontSetProcessed = false
    ): ?BpmnFlowNode {

        return parent::processNextElement($this->getFlowNode()->getDataItemValue('subProcessElementId'));
    }

    public function process(): void
    {
        $target = $this->getConditionsTarget();

        if (!$target) {
            $this->fail();

            return;
        }

        $result = $this->getConditionManager()->check(
            $target,
            $this->getAttributeValue('conditionsAll'),
            $this->getAttributeValue('conditionsAny'),
            $this->getAttributeValue('conditionsFormula'),
            $this->getVariablesForFormula()
        );

        if ($result) {
            $subProcessIsInterrupting = $this->getFlowNode()->getDataItemValue('subProcessIsInterrupting');

            if (!$subProcessIsInterrupting) {
                $this->createOppositeNode();
            }

            if ($subProcessIsInterrupting) {
                $this->getManager()->interruptProcessByEventSubProcess($this->getProcess(), $this->getFlowNode());
            }

            $this->processNextElement();

            return;
        }

        $this->getFlowNode()->set(['status' => $this->pendingStatus]);
        $this->saveFlowNode();
    }

    public function proceedPending(): void
    {
        $result = $this->getConditionManager()->check(
            $this->getTarget(),
            $this->getAttributeValue('conditionsAll'),
            $this->getAttributeValue('conditionsAny'),
            $this->getAttributeValue('conditionsFormula'),
            $this->getVariablesForFormula()
        );

        if ($this->getFlowNode()->getDataItemValue('isOpposite')) {
            if (!$result) {
                $this->setProcessed();
                $this->createOppositeNode(true);
            }

            return;
        }

        if ($result) {
            $subProcessIsInterrupting = $this->getFlowNode()->getDataItemValue('subProcessIsInterrupting');

            if (!$subProcessIsInterrupting) {
                $this->createOppositeNode();
            }

            if ($subProcessIsInterrupting) {
                $this->getManager()->interruptProcessByEventSubProcess($this->getProcess(), $this->getFlowNode());
            }

            $this->processNextElement();
        }
    }

    protected function createOppositeNode(bool $isNegative = false): void
    {
        $data = $this->getFlowNode()->getData();
        $data = clone $data;
        $data->isOpposite = !$isNegative;

        $flowNode = $this->getEntityManager()->getNewEntity(BpmnFlowNode::ENTITY_TYPE);

        $flowNode->setMultiple([
            'status' => BpmnFlowNode::STATUS_STANDBY,
            'elementType' => $this->getFlowNode()->getElementType(),
            'elementData' => $this->getFlowNode()->getElementData(),
            'data' => $data,
            'flowchartId' => $this->getProcess()->getFlowchartId(),
            'processId' => $this->getProcess()->getId(),
            'targetType' => $this->getFlowNode()->getTargetType(),
            'targetId' => $this->getFlowNode()->getTargetId(),
        ]);

        $this->getEntityManager()->saveEntity($flowNode);
    }
}
