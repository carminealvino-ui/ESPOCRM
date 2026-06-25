<?php

namespace Espo\Custom\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Controllers\Base;
use Espo\Custom\Services\CallStandardTesto as CallStandardTestoService;

class CallStandardTesto extends Base
{
    public function __construct(
        private CallStandardTestoService $service
    ) {}

    /**
     * @return array{testo: string}
     */
    public function getActionRead(Request $request): array
    {
        return [
            'testo' => $this->service->get(),
        ];
    }
}
