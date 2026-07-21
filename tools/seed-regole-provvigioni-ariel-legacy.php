#!/usr/bin/env php
<?php
/**
 * Seed regole Ariel LEGACY via ORM (affidabile anche se SQL raw fallisce).
 *
 *   php tools/run-regola-provvigionale-schema-patch.php
 *   php tools/seed-regole-provvigioni-ariel-legacy.php
 */

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

$app = new Espo\Core\Application();
$app->setupSystemUser();

$entityManager = $app->getContainer()->get('entityManager');

echo "=== Seed Ariel LEGACY (ORM) ===\n";

$rules = getArielLegacySeedRules();
$errors = 0;

foreach ($rules as $ruleData) {
    $id = $ruleData['id'];

    try {
        upsertLegacyRule($entityManager, $ruleData);
        echo "OK   {$id} | {$ruleData['regimeProvvigione']} | {$ruleData['gruppoProvvigione']} | {$ruleData['percentuale']}%\n";
    } catch (Throwable $e) {
        echo "ERR  {$id}: {$e->getMessage()}\n";
        $errors++;
    }
}

if ($errors > 0) {
    exit(1);
}

echo "Seed Ariel LEGACY completato (" . count($rules) . " regole).\n";

/**
 * @return list<array<string, mixed>>
 */
function getArielLegacySeedRules(): array
{
    return [
        legacyRule('arlCliM029', 'Ariel Clima 0% / -2,99%', 'Scaletta minus climatizzatori', 410, 'Ariel Climatizzatori', 15, -2.99, 0),
        legacyRule('arlCliM499', 'Ariel Clima -3% / -4,99%', null, 400, 'Ariel Climatizzatori', 13, -4.99, -3),
        legacyRule('arlCliM699', 'Ariel Clima -5% / -6,99%', null, 390, 'Ariel Climatizzatori', 12, -6.99, -5),
        legacyRule('arlCliM999', 'Ariel Clima -7% / -9,99%', null, 380, 'Ariel Climatizzatori', 10, -9.99, -7),
        legacyRule('arlCliM1299', 'Ariel Clima -10% / -12,99%', null, 370, 'Ariel Climatizzatori', 6, -12.99, -10),
        legacyRule('arlCliM1499', 'Ariel Clima -13% / -14,99%', null, 360, 'Ariel Climatizzatori', 3, -14.99, -13),
        legacyRule('arlCalM290', 'Ariel Caldaie 0% / -2,90%', 'Scaletta minus caldaie', 410, 'Ariel Caldaie', 13, -2.9, 0),
        legacyRule('arlCalM1000', 'Ariel Caldaie -3% / -10%', null, 400, 'Ariel Caldaie', 7, -10, -3),
        legacyRule('arlCalMDeep', 'Ariel Caldaie oltre -10,10%', null, 390, 'Ariel Caldaie', 5, -99999, -10.1),
        legacyRule('arlEcoWind5', 'Ariel Eco Wind Easy 5%', 'Pacchetto installato a prezzo fisso', 420, 'Ariel Eco Wind Easy', 5, 0, 0),
        legacyRule('arlStuM290', 'Ariel Stufe 0% / -2,90%', 'Scaletta minus stufe', 410, 'Ariel Stufe', 13, -2.9, 0),
        legacyRule('arlStuM1000', 'Ariel Stufe -3% / -10%', null, 400, 'Ariel Stufe', 7, -10, -3),
        legacyRule('arlStuMDeep', 'Ariel Stufe oltre -10,10%', null, 390, 'Ariel Stufe', 5, -99999, -10.1),
        [
            'id' => 'arlLegacyPlus50',
            'name' => 'Ariel legacy — Plus 50% oltre prezzo codice',
            'description' => 'Clima/Caldaie/Stufe (escluso Eco Wind Easy)',
            'attiva' => true,
            'priorita' => 510,
            'regimeProvvigione' => 'ARIEL_LEGACY',
            'gruppoProvvigione' => null,
            'tipoCalcolo' => 'PercentualePlusvalenza',
            'tipoProvvigioneRecord' => 'Plus Provvigionale',
            'percentuale' => 50.0,
            'margineMin' => null,
            'margineMax' => null,
        ],
    ];
}

function legacyRule(
    string $id,
    string $name,
    ?string $description,
    int $priorita,
    string $gruppo,
    float $percentuale,
    float $margineMin,
    float $margineMax
): array {
    return [
        'id' => $id,
        'name' => $name,
        'description' => $description,
        'attiva' => true,
        'priorita' => $priorita,
        'regimeProvvigione' => 'ARIEL_LEGACY',
        'gruppoProvvigione' => $gruppo,
        'tipoCalcolo' => 'PercentualeMargine',
        'tipoProvvigioneRecord' => 'Provvigione Base',
        'percentuale' => $percentuale,
        'margineMin' => $margineMin,
        'margineMax' => $margineMax,
    ];
}

/**
 * @param array<string, mixed> $ruleData
 */
function upsertLegacyRule(\Espo\ORM\EntityManager $entityManager, array $ruleData): void
{
    $id = $ruleData['id'];
    $entity = $entityManager->getEntityById('RegolaProvvigionale', $id);

    if (!$entity) {
        $entity = $entityManager->getNewEntity('RegolaProvvigionale');
        $entity->set('id', $id);
    }

    $entity->set(array_merge($ruleData, [
        'deleted' => false,
    ]));

    $entityManager->saveEntity($entity, [
        'skipHooks' => true,
        'silent' => true,
    ]);
}
