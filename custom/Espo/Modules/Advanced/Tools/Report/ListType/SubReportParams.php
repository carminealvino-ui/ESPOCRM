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

namespace Espo\Modules\Advanced\Tools\Report\ListType;

class SubReportParams
{
    /**
     * @param ?scalar $groupValue
     * @param ?scalar $groupValue2
     */
    public function __construct(
        private int $groupIndex,
        private $groupValue,
        private bool $hasGroupValue2 = false,
        private $groupValue2 = null,
        private ?string $target = null,
    ) {}

    public function getGroupIndex(): int
    {
        return $this->groupIndex;
    }

    /**
     * @return ?scalar
     */
    public function getGroupValue()
    {
        return $this->groupValue;
    }

    public function hasGroupValue2(): bool
    {
        return $this->hasGroupValue2;
    }

    /**
     * @return ?scalar
     */
    public function getGroupValue2()
    {
        return $this->groupValue2;
    }

    public function getTarget(): ?string
    {
        return $this->target;
    }
}
