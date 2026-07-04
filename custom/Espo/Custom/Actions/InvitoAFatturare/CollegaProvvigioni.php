<?php

namespace Espo\Custom\Actions\InvitoAFatturare;

use Espo\Core\Api\Request;
use Espo\Core\Exceptions\BadRequest;
use Espo\Custom\Services\InvitoAFatturareManager;
use stdClass;

class CollegaProvvigioni
{
    public function __construct(
        private InvitoAFatturareManager $manager
    ) {}

    public function run(Request $request): stdClass
    {
        $data = $request->getParsedBody();

        $invitoId = $data->invitoId ?? $data->id ?? null;
        $provvigioneIds = $data->provvigioneIds ?? [];

        if (!$invitoId) {
            throw new BadRequest('invitoId è obbligatorio.');
        }

        if (!is_array($provvigioneIds)) {
            throw new BadRequest('provvigioneIds deve essere un array.');
        }

        $result = $this->manager->collegaProvvigioni(
            (string) $invitoId,
            array_map('strval', $provvigioneIds)
        );

        return (object) $result;
    }
}
