<?php
/**
 * Inserisce/aggiorna regole provvigionali Ariel (minus, weekend, referenza).
 * Non richiede mysql CLI — usa l'ORM Espo.
 *
 *   php tools/seed-regole-provvigioni-ariel.php
 */

declare(strict_types=1);

use Espo\Core\Application;
use Espo\ORM\EntityManager;

function seedRegoleProvvigioniAriel(EntityManager $em): void
{
    $rules = [
        [
            'id' => 'arielMinus35',
            'name' => 'Ariel 2026 — 35% su minusvalenza',
            'description' => 'Minus sotto listino codice (contatore minus/plus negativo)',
            'attiva' => true,
            'priorita' => 545,
            'regimeProvvigione' => 'ARIEL_2026',
            'tipoCalcolo' => 'PercentualePlusvalenza',
            'tipoProvvigioneRecord' => 'Minus Provvigionale',
            'percentuale' => 35.0,
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

    foreach ($rules as $data) {
        $id = $data['id'];
        $entity = $em->getEntityById('RegolaProvvigionale', $id);

        if (!$entity) {
            $entity = $em->createEntity('RegolaProvvigionale');
            $entity->set('id', $id);
        }

        $entity->set(array_merge($data, [
            'deleted' => false,
        ]));

        $em->saveEntity($entity, [
            'skipHooks' => true,
            'silent' => true,
        ]);
    }
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

    foreach (['arielMinus35', 'bonusWeekendSd', 'referenzaPersonale'] as $ruleId) {
        $rule = $em->getEntityById('RegolaProvvigionale', $ruleId);
        echo $rule && !$rule->get('deleted')
            ? "OK {$ruleId} — {$rule->get('name')}\n"
            : "ERRORE {$ruleId} non trovata\n";
    }

    echo "=== Fatto ===\n";
}
