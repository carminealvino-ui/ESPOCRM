<?php

namespace Espo\Custom\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Controllers\Record;
use Espo\Custom\Services\AppuntamentoRifissatoCreator;

class Appuntamento extends Record
{
    /**
     * @return array{id: string}
     */
    public function postActionCreateRifissato(Request $request): array
    {
        $data = $request->getParsedBody();
        $sourceId = is_object($data) ? ($data->sourceId ?? null) : null;
        $dateStart = is_object($data) ? ($data->dateStart ?? null) : null;

        $creator = new AppuntamentoRifissatoCreator(
            $this->getContainer()->get('entityManager')
        );

        $id = $creator->create((string) $sourceId, (string) $dateStart);

        return ['id' => $id];
    }
}
