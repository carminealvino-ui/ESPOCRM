<?php

namespace Espo\Custom\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Templates\Controllers\Base;
use Espo\Custom\Services\CallStandardTesto as CallStandardTestoService;

class CallStandardTesto extends Base
{
    public function __construct(
        private CallStandardTestoService $service
    ) {}

    /**
     * @return array{testo: string}
     */
    public function getActionRead(Request $request, Response $response): object
    {
        return (object) [
            'testo' => $this->service->get(),
        ];
    }
}
