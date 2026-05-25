<?php

namespace Espo\Custom\Actions\InvitoAFatturare;

use Espo\Core\Api\Request;
use Espo\Core\Exceptions\BadRequest;
use Espo\Custom\Services\InvitoAFatturareManager;
use stdClass;

class GeneraDaProvvigioni
{
    public function __construct(
        private InvitoAFatturareManager $manager
    ) {}

    public function run(Request $request): stdClass
    {
        $data = $request->getParsedBody();

        $consulenteId = $data->consulenteId ?? $data->assignedUserId ?? null;
        $meseCompetenza = $data->meseCompetenza ?? null;

        if (!$consulenteId || !$meseCompetenza) {
            throw new BadRequest('consulenteId e meseCompetenza sono obbligatori.');
        }

        $result = $this->manager->generaDaProvvigioni(
            (string) $consulenteId,
            (string) $meseCompetenza,
            $data->fornitorePartnerId ?? null,
            $data->productBrandId ?? null,
            $data->id ?? $data->invitoId ?? null
        );

        return (object) $result;
    }
}
