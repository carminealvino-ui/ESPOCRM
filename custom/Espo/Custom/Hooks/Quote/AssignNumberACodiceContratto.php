<?php

namespace Espo\Custom\Hooks\Quote;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Assegna Codice Contratto automatico (numberA: Contratto_00001) quando il contratto esce da Bozza.
 */
class AssignNumberACodiceContratto implements BeforeSave
{
    public static int $order = 13;

    private const STATUS_DRAFT = 'Draft';

    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($entity->getEntityType() !== 'Quote') {
            return;
        }

        if ($options->get('silent') || $options->get('skipHooks')) {
            return;
        }

        if ($this->hasNumberA($entity)) {
            return;
        }

        if ($entity->get('status') === self::STATUS_DRAFT
            || $entity->get('status') === 'Bozza'
        ) {
            return;
        }

        $entity->set('numberA', $this->buildNextNumberA());
    }

    private function hasNumberA(Entity $entity): bool
    {
        $value = $entity->get('numberA');

        if ($value === null) {
            return false;
        }

        return trim((string) $value) !== '';
    }

    private function buildNextNumberA(): string
    {
        $defs = $this->entityManager
            ->getMetadata()
            ->get(['entityDefs', 'Quote', 'fields', 'numberA']) ?? [];

        $prefix = (string) ($defs['prefix'] ?? 'Contratto_');
        $padLength = (int) ($defs['padLength'] ?? 5);
        $nextFromDefs = (int) ($defs['nextNumber'] ?? 1);

        $max = $this->resolveMaxSequence($prefix);
        $next = max($nextFromDefs, $max + 1);

        return $prefix . str_pad((string) $next, $padLength, '0', STR_PAD_LEFT);
    }

    private function resolveMaxSequence(string $prefix): int
    {
        $pdo = $this->entityManager->getPDO();
        $like = $prefix . '%';

        $stmt = $pdo->prepare(
            "SELECT number_a
             FROM quote
             WHERE deleted = 0
               AND number_a IS NOT NULL
               AND number_a LIKE :like
             ORDER BY number_a DESC
             LIMIT 1"
        );
        $stmt->execute(['like' => $like]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$row || empty($row['number_a'])) {
            return 0;
        }

        $digits = preg_replace('/\D/', '', (string) $row['number_a']);

        return $digits !== '' ? (int) $digits : 0;
    }
}
