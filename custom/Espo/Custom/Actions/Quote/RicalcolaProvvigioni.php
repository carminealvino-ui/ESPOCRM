<?php

namespace Espo\Custom\Actions\Quote;

use Espo\Core\Api\Request;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\NotFound;
use Espo\Custom\Services\ProvvigioneManager;
use Espo\ORM\EntityManager;
use stdClass;

class RicalcolaProvvigioni
{
    public function __construct(
        private EntityManager $entityManager,
        private ProvvigioneManager $provvigioneManager
    ) {}

    public function run(Request $request): stdClass
    {
        $data = $request->getParsedBody();
        $id = $data->id ?? null;

        if (!$id) {
            throw new BadRequest('ID contratto mancante.');
        }

        $quote = $this->entityManager->getEntityById('Quote', (string) $id);

        if (!$quote) {
            throw new NotFound('Contratto non trovato.');
        }

        if (!$quote->get('opportunityId')) {
            throw new BadRequest('Collegare un\'opportunità al contratto prima del ricalcolo.');
        }

        $result = $this->provvigioneManager->recalculateAllForQuote($quote);

        $quote = $this->entityManager->getEntityById('Quote', $quote->getId());

        $count = $this->entityManager
            ->getRDBRepository('Provvigione')
            ->where(['contrattoId' => $quote->getId()])
            ->count();

        return (object) [
            'id' => $quote->getId(),
            'count' => $count,
            'created' => $result['created'],
            'purged' => $result['purged'],
            'totaleProvvigioni' => $quote->get('totaleProvvigioni'),
        ];
    }
}
