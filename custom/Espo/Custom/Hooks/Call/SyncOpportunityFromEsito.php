<?php

namespace Espo\Custom\Hooks\Call;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Custom\Tools\DateTime\BusinessDateTime;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Esito "Non interessato" sul risconto richiamo: opportunità collegata persa
 * e appuntamento Pending aggiornato a Non Interessato.
 */
class SyncOpportunityFromEsito implements AfterSave
{
    private const NOTA_PREFIX = 'Auto-Pending-Appuntamento:';
    private const ESITO_NON_INTERESSATO = 'Non interessato';
    private const APPUNTAMENTO_NON_INTERESSATO = 'Non Interessato';
    private const STAGE_LOST = 'Closed Lost';

    /** @var string[] */
    private const TERMINAL_STAGES = [
        'Closed Won',
        'Closed Lost',
        'Chiusa persa',
        'Chiuso Negativamente',
    ];

    public static int $order = 11;

    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get('skipHooks') || $options->get('isImport')) {
            return;
        }

        if ($entity->getEntityType() !== 'Call') {
            return;
        }

        if (!in_array($entity->get('status'), ['Held', 'Not Held'], true)) {
            return;
        }

        if ($entity->get('esito') !== self::ESITO_NON_INTERESSATO) {
            return;
        }

        $this->closeLinkedOpportunities($entity);
        $this->updatePendingAppuntamento($entity);
    }

    private function closeLinkedOpportunities(Entity $call): void
    {
        foreach ($this->resolveOpportunityIds($call) as $opportunityId) {
            $opportunity = $this->entityManager->getEntityById('Opportunity', $opportunityId);

            if (!$opportunity) {
                continue;
            }

            $stage = (string) $opportunity->get('stage');

            if (in_array($stage, self::TERMINAL_STAGES, true)) {
                continue;
            }

            $today = (new \DateTimeImmutable('now', new \DateTimeZone(BusinessDateTime::BUSINESS_TIMEZONE)))
                ->format('Y-m-d');

            $opportunity->set([
                'stage' => self::STAGE_LOST,
                'closeDate' => $today,
            ]);

            $this->entityManager->saveEntity($opportunity, [
                'silent' => true,
            ]);
        }
    }

    private function updatePendingAppuntamento(Entity $call): void
    {
        $appuntamentoId = $this->extractAppuntamentoId((string) $call->get('nota'));

        if (!$appuntamentoId) {
            return;
        }

        $appuntamento = $this->entityManager->getEntityById('Appuntamento', $appuntamentoId);

        if (!$appuntamento) {
            return;
        }

        if ($appuntamento->get('sottostato') !== 'Pending') {
            return;
        }

        $appuntamento->set('sottostato', self::APPUNTAMENTO_NON_INTERESSATO);

        $this->entityManager->saveEntity($appuntamento, [
            'silent' => true,
        ]);
    }

    /**
     * @return string[]
     */
    private function resolveOpportunityIds(Entity $call): array
    {
        $ids = [];

        if ($call->get('parentType') === 'Opportunity' && $call->get('parentId')) {
            $ids[] = (string) $call->get('parentId');
        }

        $appuntamentoId = $this->extractAppuntamentoId((string) $call->get('nota'));

        if ($appuntamentoId) {
            $collection = $this->entityManager
                ->getRDBRepository('Opportunity')
                ->select(['id'])
                ->where(['appuntamentoId' => $appuntamentoId])
                ->find();

            foreach ($collection as $opportunity) {
                $ids[] = $opportunity->getId();
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }

    private function extractAppuntamentoId(string $nota): ?string
    {
        if (!str_contains($nota, self::NOTA_PREFIX)) {
            return null;
        }

        if (!preg_match('/' . preg_quote(self::NOTA_PREFIX, '/') . '\s*([a-z0-9]{17})/i', $nota, $matches)) {
            return null;
        }

        return $matches[1];
    }
}
