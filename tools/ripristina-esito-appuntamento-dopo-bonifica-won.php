<?php

/**
 * Ripristina esiti storici sovrascritti per errore dalla prima bonifica
 * (record già Held+Chiuso Positivamente: l'esito non andava toccato).
 *
 *   php tools/ripristina-esito-appuntamento-dopo-bonifica-won.php --dry-run
 *   php tools/ripristina-esito-appuntamento-dopo-bonifica-won.php
 */

require_once dirname(__DIR__) . '/bootstrap.php';

use Espo\Core\Application;

$dryRun = in_array('--dry-run', $argv ?? [], true);

/** @var array<string, string> appuntamentoId => esito originale (dal dry-run v1) */
$restore = [
    '658c70b203b26c04f' => 'Annullato Azienda', // NICOLETTA BORSEI
    '658c70b205126b7f5' => 'Annullato Azienda', // SIMONA PUPI
    '658c77b9c1f2caa31' => 'Annullato Azienda', // FABIO BERELLINI
    '658c77b9c33e132c2' => 'Annullato Azienda', // AGOSTINO PIERUCCI
    '658c77b9c47b1e1e0' => 'Annullato Azienda', // MAURO TANGARI
    '669e74511d58927c7' => 'Ripasso per info', // FRANCESCO DI ROMANO
    '69b2c3b74258b8a63' => 'Appuntamento non in agenda', // ALESSANDRINI BIBIANA
];

$app = new Application();
$app->setupSystemUser();
$em = $app->getContainer()->get('entityManager');

$updated = 0;
$skipped = 0;

foreach ($restore as $appId => $esito) {
    $entity = $em->getEntityById('Appuntamento', $appId);

    if (!$entity) {
        echo "SKIP missing {$appId}\n";
        $skipped++;
        continue;
    }

    $current = (string) ($entity->get('esito') ?? '');

    if ($current === $esito) {
        echo "SKIP already {$appId} esito={$esito}\n";
        $skipped++;
        continue;
    }

    if ($current !== 'Venduto Cartaceo' && $current !== '') {
        echo "SKIP unexpected {$appId} esito={$current}\n";
        $skipped++;
        continue;
    }

    echo ($dryRun ? 'DRY ' : 'OK  ')
        . $appId . ' ' . $entity->get('name')
        . " esito {$current} → {$esito}"
        . PHP_EOL;

    if (!$dryRun) {
        $entity->set('esito', $esito);
        $em->saveEntity($entity, [
            'silent' => true,
            'skipHooks' => true,
        ]);
    }

    $updated++;
}

echo PHP_EOL . "Ripristinati={$updated} skip={$skipped}" . ($dryRun ? ' (dry-run)' : '') . PHP_EOL;
