#!/usr/bin/env php
<?php
/**
 * Copia finanziamento / stato contratto dall'opportunità collegata ai contratti vuoti.
 *
 *   cd ~/public_html/crm/mec-group
 *   php tools/backfill-quote-finanziamento-da-opportunita.php
 */

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

$app = new Espo\Core\Application();
$app->setupSystemUser();

$em = $app->getContainer()->get('entityManager');

$collection = $em
    ->getRDBRepository('Quote')
    ->where(['deleted' => false])
    ->find();

$updated = 0;

foreach ($collection as $quote) {
    $opportunityId = $quote->get('opportunityId');

    if (!$opportunityId) {
        continue;
    }

    $opportunity = $em->getEntityById('Opportunity', $opportunityId);

    if (!$opportunity) {
        continue;
    }

    $patch = [];

    if ($quote->get('finanziamento') === null && $opportunity->get('finanziamento') !== null) {
        $patch['finanziamento'] = (bool) $opportunity->get('finanziamento');
    }

    foreach (['statoFinanziamento', 'statoContratto'] as $field) {
        $quoteValue = $quote->get($field);
        $oppValue = $opportunity->get($field);

        if (($quoteValue === null || $quoteValue === '') && $oppValue !== null && $oppValue !== '') {
            $patch[$field] = $oppValue;
        }
    }

    if ($patch === []) {
        continue;
    }

    $quote->set($patch);

    $em->saveEntity($quote, [
        'skipHooks' => true,
        'silent' => true,
        'skipFormula' => true,
    ]);

    $updated++;
}

echo "Contratti aggiornati da opportunità: {$updated}\n";
