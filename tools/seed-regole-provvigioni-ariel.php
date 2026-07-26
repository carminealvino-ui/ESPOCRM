<?php
/**
 * Inserisce/aggiorna regole provvigionali Ariel (minus, weekend, taxi, referenza).
 * Non richiede mysql CLI — usa l'ORM Espo (+ PDO fallback per ID fissi).
 *
 *   php tools/seed-regole-provvigioni-ariel.php
 */

declare(strict_types=1);

use Espo\Core\Application;
use Espo\ORM\EntityManager;

/**
 * @return list<array<string, mixed>>
 */
function getArielProvvigioniSeedRules(): array
{
    return [
        [
            'id' => 'arielMinus35',
            'name' => 'Ariel 2026 — 100% su minusvalenza',
            'description' => 'Minus sotto listino codice: decremento al 100% della minusvalenza',
            'attiva' => true,
            'priorita' => 545,
            'regimeProvvigione' => 'ARIEL_2026',
            'tipoCalcolo' => 'PercentualeMinusvalenza',
            'tipoProvvigioneRecord' => 'Minus Provvigionale',
            'percentuale' => 100.0,
        ],
        [
            'id' => 'bonusWeekendSd',
            'name' => 'Bonus Sabato-Domenica',
            'description' => 'Extra provvigione se data contratto/appuntamento cade sabato o domenica',
            'attiva' => true,
            'priorita' => 530,
            'regimeProvvigione' => '',
            'tipoCalcolo' => 'PercentualeImponibile',
            'tipoProvvigioneRecord' => 'Bonus (Sabato-Domenica)',
            'percentuale' => 2.0,
        ],
        [
            'id' => 'bonusEstate2026',
            'name' => 'ESTATE 2026',
            'description' => 'Overcompenso fisso 50 € per ogni contratto di sabato/domenica nei mesi di luglio e agosto 2026',
            'attiva' => true,
            'priorita' => 535,
            'regimeProvvigione' => '',
            'tipoCalcolo' => 'GettoneFisso',
            'tipoProvvigioneRecord' => 'Bonus Estate 2026',
            'gettoneImporto' => 50.0,
        ],
        [
            'id' => 'bonusTaxi2',
            'name' => 'Bonus Taxi',
            'description' => 'Extra provvigione 2% se appuntamento con flag Taxi',
            'attiva' => true,
            'priorita' => 525,
            'regimeProvvigione' => '',
            'tipoCalcolo' => 'PercentualeImponibile',
            'tipoProvvigioneRecord' => 'Bonus Taxi',
            'percentuale' => 2.0,
        ],
        [
            'id' => 'referenzaPersonale',
            'name' => 'Referenza Personale',
            'description' => 'Appuntamento con tipo Referenza Personale — 6% su imponibile',
            'attiva' => true,
            'priorita' => 620,
            'regimeProvvigione' => 'ARIEL_2026',
            'tipoCalcolo' => 'PercentualeImponibile',
            'tipoProvvigioneRecord' => 'Referenza Personale',
            'percentuale' => 6.0,
        ],
    ];
}

function seedRegoleProvvigioniAriel(EntityManager $em): void
{
    foreach (getArielProvvigioniSeedRules() as $data) {
        upsertArielRule($em, $data);
    }
}

/**
 * @param array<string, mixed> $data
 */
function upsertArielRule(EntityManager $em, array $data): void
{
    $id = (string) $data['id'];

    try {
        $entity = $em->getEntityById('RegolaProvvigionale', $id);

        if (!$entity) {
            // getNewEntity: non salva; createEntity salverebbe con ID random.
            $entity = $em->getNewEntity('RegolaProvvigionale');
            $entity->set('id', $id);
        }

        $entity->set(array_merge($data, [
            'deleted' => false,
        ]));

        $em->saveEntity($entity, [
            'skipHooks' => true,
            'silent' => true,
        ]);

        $saved = $em->getEntityById('RegolaProvvigionale', $id);

        if ($saved && !$saved->get('deleted')) {
            return;
        }
    } catch (Throwable $e) {
        fwrite(STDERR, "WARN ORM {$id}: {$e->getMessage()}\n");
    }

    upsertArielRuleViaPdo($em, $data);
}

/**
 * Fallback SQL se l'ORM rifiuta enum/cache non aggiornata.
 *
 * @param array<string, mixed> $data
 */
function upsertArielRuleViaPdo(EntityManager $em, array $data): void
{
    $pdo = $em->getPDO();
    $id = (string) $data['id'];

    $sql = <<<'SQL'
INSERT INTO regola_provvigionale (
    id, name, description, deleted, attiva, priorita,
    regime_provvigione, tipo_calcolo, tipo_provvigione_record, percentuale,
    gettone_importo,
    created_at, modified_at
) VALUES (
    :id, :name, :description, 0, :attiva, :priorita,
    :regime, :tipo_calcolo, :tipo_record, :percentuale,
    :gettone,
    NOW(), NOW()
)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    deleted = 0,
    attiva = VALUES(attiva),
    priorita = VALUES(priorita),
    regime_provvigione = VALUES(regime_provvigione),
    tipo_calcolo = VALUES(tipo_calcolo),
    tipo_provvigione_record = VALUES(tipo_provvigione_record),
    percentuale = VALUES(percentuale),
    gettone_importo = VALUES(gettone_importo),
    modified_at = NOW()
SQL;

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':id' => $id,
        ':name' => (string) $data['name'],
        ':description' => (string) ($data['description'] ?? ''),
        ':attiva' => !empty($data['attiva']) ? 1 : 0,
        ':priorita' => (int) ($data['priorita'] ?? 100),
        ':regime' => (string) ($data['regimeProvvigione'] ?? ''),
        ':tipo_calcolo' => (string) ($data['tipoCalcolo'] ?? ''),
        ':tipo_record' => (string) ($data['tipoProvvigioneRecord'] ?? ''),
        ':percentuale' => isset($data['percentuale']) ? (float) $data['percentuale'] : null,
        ':gettone' => isset($data['gettoneImporto']) ? (float) $data['gettoneImporto'] : null,
    ]);
}

if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === realpath(__FILE__)) {
    $crmRoot = getenv('CRM_ROOT') ?: (getenv('HOME') . '/public_html/crm/mec-group');

    if (!is_dir($crmRoot)) {
        $crmRoot = dirname(__DIR__);
    }

    require_once $crmRoot . '/bootstrap.php';

    $app = new Application();
    $app->setupSystemUser();

    /** @var EntityManager $em */
    $em = $app->getContainer()->getByClass(EntityManager::class);

    echo "=== Seed regole provvigionali Ariel ===\n";
    seedRegoleProvvigioniAriel($em);

    $errors = 0;

    foreach (['arielMinus35', 'bonusWeekendSd', 'bonusEstate2026', 'bonusTaxi2', 'referenzaPersonale'] as $ruleId) {
        $rule = $em->getEntityById('RegolaProvvigionale', $ruleId);

        if ($rule && !$rule->get('deleted')) {
            echo "OK {$ruleId} — {$rule->get('name')}\n";
            continue;
        }

        // Rileggi via SQL se ORM ha cache stantia
        $stmt = $em->getPDO()->prepare(
            'SELECT id, name, deleted FROM regola_provvigionale WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$ruleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && (int) ($row['deleted'] ?? 0) === 0) {
            echo "OK {$ruleId} — {$row['name']} (PDO)\n";
            continue;
        }

        echo "ERRORE {$ruleId} non trovata\n";
        $errors++;
    }

    echo "=== Fatto ===\n";

    if ($errors > 0) {
        exit(1);
    }
}
