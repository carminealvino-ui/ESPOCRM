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

namespace Espo\Modules\Advanced\Core\Bpmn\Utils;

use Espo\Core\Acl\GlobalRestriction;
use Espo\Core\AclManager;
use Espo\ORM\Entity;
use stdClass;

class PlaceholderHelper
{
    public function __construct(
        private AclManager $aclManager,
    ) {}

    public function apply(string $text, Entity $target, ?stdClass $variables = null): string
    {
        $restrictedAttributes = array_merge(
            $this->aclManager->getScopeRestrictedFieldList($target->getEntityType(), GlobalRestriction::TYPE_FORBIDDEN),
            $this->aclManager->getScopeRestrictedFieldList($target->getEntityType(), GlobalRestriction::TYPE_INTERNAL),
        );

        foreach ($target->getAttributeList() as $attribute) {
            if (in_array($attribute, $restrictedAttributes)) {
                continue;
            }

            $value = $target->get($attribute);

            if ($value === null) {
                continue;
            }

            if (is_numeric($value)) {
                $value = (string) $value;
            }

            if (!is_string($value)) {
                continue;
            }

            $text = str_replace('{$' . $attribute . '}', $value, $text);
        }

        $variables ??= (object) [];

        foreach (get_object_vars($variables) as $key => $value) {
            if (is_numeric($value)) {
                $value = (string) $value;
            }

            if (!is_string($value)) {
                continue;
            }

            $text = str_replace('{$$' . $key . '}', $value, $text);
        }

        return $text;
    }
}
