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
 * License ID: 77350457a8d35522431c4daeee1dd4ad
 ************************************************************************************/

namespace Espo\Modules\Outlook\Core\Outlook\Exceptions;

use Espo\Core\Exceptions\Error;

class ApiError extends Error
{
    protected $result;

    protected $originalCode;

    public static function create(?string $message = null, ?array $result = null, ?int $originalCode = null): self
    {
        $obj = new self($message);

        $obj->result = $result;
        $obj->originalCode = $originalCode;

        return $obj;
    }

    public function getResult() : array
    {
        return $this->result ?? [];
    }

    public function getOriginalCode() : ?int
    {
        return $this->originalCode;
    }
}
