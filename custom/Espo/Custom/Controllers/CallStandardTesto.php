<?php

namespace Espo\Custom\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Custom\Services\CallStandardTesto as CallStandardTestoService;

class CallStandardTesto
{
    public function __construct(
        private CallStandardTestoService $service
    ) {}

    public function getActionRead(Request $request, Response $response): object
    {
        return (object) [
            'testo' => $this->service->get(),
        ];
    }
}
