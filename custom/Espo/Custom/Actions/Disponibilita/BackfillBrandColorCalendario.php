<?php

namespace Espo\Custom\Actions\Disponibilita;

use Espo\Custom\Services\BrandCalendarColorBackfill;
use Espo\ORM\EntityManager;

class BackfillBrandColorCalendario
{
    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function run(object $data): object
    {
        $service = new BrandCalendarColorBackfill($this->entityManager);

        $stats = $service->run([
            'dryRun' => (bool) ($data->dryRun ?? false),
            'only' => $data->only ?? 'all',
            'applyDefaultColors' => (bool) ($data->applyDefaultColors ?? false),
            'colorsJsonPath' => $data->colorsJsonPath ?? null,
            'limit' => (int) ($data->limit ?? 0),
            'forceColor' => (bool) ($data->forceColor ?? false),
        ]);

        return (object) [
            ...$stats,
            'message' => sprintf(
                'Brand colorati: %d, calendari: %d, disponibilità: %d, appuntamenti: %d%s.',
                $stats['brandsColored'],
                $stats['calendarsUpdated'],
                $stats['disponibilitaUpdated'],
                $stats['appuntamentiUpdated'],
                $stats['dryRun'] ? ' (dry-run)' : ''
            ),
        ];
    }
}
