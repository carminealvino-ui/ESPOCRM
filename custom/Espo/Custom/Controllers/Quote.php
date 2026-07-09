<?php

namespace Espo\Custom\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Controllers\Record;
use Espo\Core\InjectableFactory;

class Quote extends Record
{
    public function postActionRicalcolaProvvigioni(Request $request, Response $response): object
    {
        /** @var InjectableFactory $injectableFactory */
        $injectableFactory = $this->getContainer()->get('injectableFactory');

        return $injectableFactory
            ->create(\Espo\Custom\Actions\Quote\RicalcolaProvvigioni::class)
            ->run($request);
    }
}
