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

use Espo\Core\Acl\GlobalRestriction;
use Espo\Core\AclManager;
use Espo\Core\Exceptions\Error\Body;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Utils\Acl\UserAclManagerProvider;
use Espo\Entities\User;
use Espo\ORM\Defs;
use LogicException;

class AccessHelper
{
    public function __construct(
        private UserAclManagerProvider $userAclManagerProvider,
        private AclManager $aclManager,
        private Defs $defs,

    ) {}

    /**
     * @throws Forbidden
     */
    public function assertAccessToAttribute(?User $user, string $entityType, string $attribute): void
    {
        if (str_contains($attribute, '.')) {
            $this->assertAccessToAttributeForeign($entityType, $user, $attribute);

            return;
        }

        $forbiddenAttributes = $this->getEntityTypeForbiddenAttributes($user, $entityType);

        if (in_array($attribute, $forbiddenAttributes)) {
            $this->throwForbiddenAttribute($attribute);
        }
    }

    /**
     * @return string[]
     */
    public function getEntityTypeForbiddenAttributes(?User $user, string $entityType): array
    {
        if ($user) {
            return $this->userAclManagerProvider
                ->get($user)
                ->getScopeForbiddenAttributeList($user, $entityType);
        }

        $attributes = array_merge(
            $this->aclManager->getScopeRestrictedAttributeList($entityType, GlobalRestriction::TYPE_FORBIDDEN),
            $this->aclManager->getScopeRestrictedAttributeList($entityType, GlobalRestriction::TYPE_INTERNAL),
        );

        $attributes = array_unique($attributes);

        return array_values($attributes);
    }

    /**
     * @return string[]
     */
    public function getEntityTypeForbiddenFields(?User $user, string $entityType): array
    {
        if ($user) {
            return $this->userAclManagerProvider
                ->get($user)
                ->getScopeForbiddenFieldList($user, $entityType);
        }

        $fields = array_merge(
            $this->aclManager->getScopeRestrictedFieldList($entityType, GlobalRestriction::TYPE_FORBIDDEN),
            $this->aclManager->getScopeRestrictedFieldList($entityType, GlobalRestriction::TYPE_INTERNAL),
        );

        $fields = array_unique($fields);

        return array_values($fields);
    }

    /**
     * @return string[]
     */
    public function getEntityTypeRestrictedLinks(?User $user, string $entityType): array
    {
        $restrictedLinks = array_merge(
            $this->aclManager->getScopeRestrictedLinkList($entityType, GlobalRestriction::TYPE_INTERNAL),
            $this->aclManager->getScopeRestrictedLinkList($entityType, GlobalRestriction::TYPE_FORBIDDEN),
        );

        $restrictedLinks = array_unique($restrictedLinks);

        return array_values($restrictedLinks);
    }

    /**
     * @throws Forbidden
     */
    private function throwForbiddenAttribute(string $attribute): never
    {
        throw Forbidden::createWithBody(
            'noAccessToAttribute',
            Body::create()
                ->withMessageTranslation('noAccessToAttribute', 'Report', ['attribute' => $attribute])
        );
    }

    /**
     * @throws Forbidden
     */
    private function assertAccessToAttributeForeign(string $entityType, ?User $user, string $attribute): void
    {
        $entityDefs = $this->defs->getEntity($entityType);

        $forbiddenFields = $this->getEntityTypeForbiddenFields($user, $entityType);
        $restrictedLinks = $this->getEntityTypeRestrictedLinks($user, $entityType);

        if (!str_contains($attribute, '.')) {
            throw new LogicException();
        }

        [$link, $foreignAttribute] = explode('.', $attribute);

        if (in_array($link, $forbiddenFields) || in_array($link, $restrictedLinks)) {
            $this->throwForbiddenAttribute($attribute);
        }

        $foreignEntityType = $entityDefs->tryGetRelation($link)?->tryGetForeignEntityType();

        if (
            $foreignAttribute &&
            in_array(
                $foreignAttribute,
                $this->getEntityTypeForbiddenAttributes($user, $foreignEntityType)
            )
        ) {
            $this->throwForbiddenAttribute($attribute);
        }
    }
}
