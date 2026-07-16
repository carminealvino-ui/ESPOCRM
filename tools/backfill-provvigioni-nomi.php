<?php
/**
 * Forza i nomi Provvigione al formato:
 *   codice contratto - nome cliente - tipo - importo consolidato
 *
 * Non dipende dagli hook: calcola e scrive il nome direttamente.
 *
 *   php tools/backfill-provvigioni-nomi.php
 *   php tools/backfill-provvigioni-nomi.php --dry-run
 *   php tools/backfill-provvigioni-nomi.php --force   # aggiorna TUTTI i record
 */

declare(strict_types=1);

$crmRoot = getenv('CRM_ROOT') ?: (getenv('HOME') . '/public_html/crm/mec-group');

if (!is_dir($crmRoot)) {
    $crmRoot = dirname(__DIR__);
}

chdir($crmRoot);
require_once $crmRoot . '/bootstrap.php';

use Espo\Core\Application;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

$dryRun = in_array('--dry-run', $argv ?? [], true);
$force = in_array('--force', $argv ?? [], true);

$app = new Application();
$app->setupSystemUser();

/** @var EntityManager $em */
$em = $app->getContainer()->get('entityManager');

$resolveCodice = static function (Entity $quote): string {
    $quoteName = trim((string) ($quote->get('name') ?? ''));

    if ($quoteName !== '' && preg_match('/^(Contratto[_\s][^\s\-]+)/i', $quoteName, $m)) {
        return str_replace(' ', '_', $m[1]);
    }

    foreach (['numberA', 'number', 'numeroContratto'] as $field) {
        $value = trim((string) ($quote->get($field) ?? ''));

        if ($value === '') {
            continue;
        }

        if (preg_match('/^(Contratto[_\s][^\s\-]+)/i', $value, $m)) {
            return str_replace(' ', '_', $m[1]);
        }

        if (preg_match('/^Contratto/i', $value)) {
            return $value;
        }

        return 'Contratto_' . ltrim($value, '_');
    }

    return 'Contratto_' . $quote->getId();
};

$buildName = static function (string $codice, string $cliente, string $tipo, mixed $importo): string {
    $importoLabel = '€. ' . number_format((float) ($importo ?? 0), 2, '.', '');
    $cliente = strtoupper(trim($cliente));

    if ($cliente === '') {
        $cliente = 'CLIENTE';
    }

    return sprintf(
        '%s - %s - %s - %s',
        $codice,
        $cliente,
        strtoupper($tipo !== '' ? $tipo : 'Provvigione Base'),
        $importoLabel
    );
};

$collection = $em->getRDBRepository('Provvigione')->find();

$updated = 0;
$skipped = 0;
$examples = [];

foreach ($collection as $provvigione) {
    $oldName = trim((string) ($provvigione->get('name') ?? ''));
    $tipo = (string) ($provvigione->get('tipo') ?: 'Provvigione Base');
    $importo = $provvigione->get('importoConsolidato');

    if ($importo === null || $importo === '') {
        $importo = $provvigione->get('importo');
    }

    $quote = null;
    $contrattoId = $provvigione->get('contrattoId');

    if ($contrattoId) {
        $quote = $em->getEntityById('Quote', $contrattoId);
    }

    if (!$quote && $provvigione->get('opportunitaId')) {
        $quote = $em->getRDBRepository('Quote')
            ->where(['opportunityId' => $provvigione->get('opportunitaId')])
            ->order('createdAt', 'DESC')
            ->findOne();
    }

    if ($quote) {
        $codice = $resolveCodice($quote);
        $cliente = (string) (
            $quote->get('accountName')
            ?? $provvigione->get('clienteName')
            ?? 'Cliente'
        );

        if (!$provvigione->get('contrattoId')) {
            $provvigione->set([
                'contrattoId' => $quote->getId(),
                'contrattoName' => $quote->get('name'),
            ]);
        }
    } else {
        $codice = 'SENZA-CONTRATTO';
        $cliente = (string) ($provvigione->get('clienteName') ?? 'Cliente');
    }

    $newName = $buildName($codice, $cliente, $tipo, $importo);

    $needsFix = $force
        || $oldName === ''
        || $oldName !== $newName
        || !preg_match('/^Contratto[_ ]/i', $oldName);

    if (!$needsFix) {
        $skipped++;
        continue;
    }

    if (count($examples) < 5) {
        $examples[] = ($oldName !== '' ? $oldName : '(vuoto)') . '  =>  ' . $newName;
    }

    if ($dryRun) {
        echo '[DRY] ' . ($oldName !== '' ? $oldName : '(vuoto)') . ' => ' . $newName . "\n";
        $updated++;
        continue;
    }

    $provvigione->set('name', $newName);
    // silent: evita ricalcoli; il nome è già definitivo
    $em->saveEntity($provvigione, ['silent' => true, 'skipHooks' => true]);
    echo '[OK] ' . $newName . "\n";
    $updated++;
}

echo "\nAggiornate: {$updated}, già ok: {$skipped}\n";

if ($examples !== []) {
    echo "\nEsempi:\n";
    foreach ($examples as $line) {
        echo '  - ' . $line . "\n";
    }
}
