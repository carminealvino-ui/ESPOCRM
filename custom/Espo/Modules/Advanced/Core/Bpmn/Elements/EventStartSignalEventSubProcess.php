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

use Espo\Modules\Advanced\Core\SignalManager;
use Espo\Modules\Advanced\Entities\BpmnFlowNode;

/**
 * @noinspection PhpUnused
 */
class EventStartSignalEventSubProcess extends Event
{
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
        $signal = $this->getSignal();

        if (!$signal) {
            $this->fail();

            $this->getLog()->warning("BPM: No signal for sub-process start event; {id}.", [
                'id' => $this->getProcess()->getId(),
            ]);

            return;
        }

        $flowNode = $this->getFlowNode();
        $flowNode->setStatus(BpmnFlowNode::STATUS_STANDBY);
        $this->getEntityManager()->saveEntity($flowNode);

        $this->getSignalManager()->subscribe($signal, $flowNode->getId());
    }

    public function proceedPending(): void
    {
        $subProcessIsInterrupting = $this->getFlowNode()->getDataItemValue('subProcessIsInterrupting');

        if (!$subProcessIsInterrupting) {
            $this->createCopy();
        }

        if ($subProcessIsInterrupting) {
            $this->getManager()->interruptProcessByEventSubProcess($this->getProcess(), $this->getFlowNode());
        }

        $this->processNextElement();
    }

    protected function createCopy(): void
    {
        $data = $this->getFlowNode()->getData();
        $data = clone $data;

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

        $this->getSignalManager()->subscribe($this->getSignal(), $flowNode->getId());
    }

    protected function getSignal(): ?string
    {
        $subProcessStartData = $this->getFlowNode()->getDataItemValue('subProcessStartData') ?? (object) [];

        $name = $subProcessStartData->signal ?? null;

        if (!$name || !is_string($name)) {
            return null;
        }

        return $this->placeholderHelper->apply($name, $this->getTarget(), $this->getVariables());
    }

    protected function getSignalManager(): SignalManager
    {
        return $this->getContainer()->getByClass(SignalManager::class);
    }
}
