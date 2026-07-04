<?php

namespace Espo\Custom\Actions\InvitoAFatturare;

use Espo\Core\Api\Request;
use Espo\Core\Exceptions\BadRequest;
use Espo\Custom\Services\InvitoAFatturareManager;
use stdClass;

class GetProvvigioniEleggibili
{
    public function __construct(
        private InvitoAFatturareManager $manager
    ) {}

    public function run(Request $request): stdClass
    {
        $data = $request->getParsedBody() ?? (object) [];

        if ($request->getMethod() === 'GET') {
            $data = (object) array_merge(
                (array) $data,
                $request->getQueryParams()
            );
        }

        $consulenteId = $data->consulenteId ?? $data->assignedUserId ?? null;
        $meseCompetenza = $data->meseCompetenza ?? null;

        if (!$consulenteId || !$meseCompetenza) {
            throw new BadRequest('consulenteId e meseCompetenza sono obbligatori.');
        }

        $result = $this->manager->getProvvigioniEleggibili(
            (string) $consulenteId,
            (string) $meseCompetenza,
            $data->fornitorePartnerId ?? null,
            $data->productBrandId ?? null,
            $data->invitoId ?? $data->id ?? null
        );

        return (object) $result;
    }
}
