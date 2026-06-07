#!/usr/bin/env php
<?php
/**
 * Backfill Brand + colore calendario (ProductBrand, calendari lavorativi,
 * Disponibilità, Appuntamenti).
 *
 * Eseguire dalla root CRM (dove esiste bootstrap.php):
 *
 *   cd ~/public_html/crm/mec-group
 *   php tools/backfill-brand-color-calendario.php --dry-run
 *   php tools/backfill-brand-color-calendario.php --apply-default-colors
 *   php tools/backfill-brand-color-calendario.php --colors-json=tools/data/brand-calendar-colors.json
 *   php tools/backfill-brand-color-calendario.php --only=disponibilita --limit=500
 *
 * Opzioni:
 *   --dry-run                 Simula senza salvare
 *   --only=SECTION            brands|calendars|disponibilita|appuntamenti|all (default all)
 *   --apply-default-colors    Usa tools/data/brand-calendar-colors.json (o .example.json)
 *   --colors-json=PATH        File JSON nomeBrand => #hex
 *   --limit=N                 Max record per sezione (0 = tutti)
 *   --force-color             Sovrascrive colori già presenti
 *   --verbose                 Dettaglio brand e motivi skip
 *   --report                  Solo diagnostica (brand, azienda, match)
 */
declare(strict_types=1);

$root = getenv('CRM_ROOT') ?: getcwd();

if (!is_file($root . '/bootstrap.php')) {
    fwrite(STDERR, "Eseguire da root CRM (bootstrap.php non trovato).\n");
    exit(1);
}

require_once $root . '/bootstrap.php';

use Espo\Core\Application;
use Espo\Custom\Services\BrandCalendarColorBackfill;

$options = getopt('', [
    'dry-run',
    'only::',
    'apply-default-colors',
    'colors-json::',
    'limit::',
    'force-color',
    'verbose',
    'report',
]);

$dryRun = array_key_exists('dry-run', $options);
$only = $options['only'] ?? 'all';
$applyDefaultColors = array_key_exists('apply-default-colors', $options);
$colorsJsonPath = $options['colors-json'] ?? null;
$limit = isset($options['limit']) ? (int) $options['limit'] : 0;
$forceColor = array_key_exists('force-color', $options);
$verbose = array_key_exists('verbose', $options);

$allowedOnly = ['all', 'brands', 'calendars', 'disponibilita', 'appuntamenti'];

if (!in_array($only, $allowedOnly, true)) {
    fwrite(STDERR, "Valore --only non valido: {$only}\n");
    exit(1);
}

$app = new Application();
$app->setupSystemUser();

$entityManager = $app->getContainer()->get('entityManager');
$service = new BrandCalendarColorBackfill($entityManager);

fwrite(STDOUT, "=== Backfill Brand / colore calendario ===\n");
fwrite(STDOUT, ($dryRun ? "[DRY-RUN] " : '') . "Sezione: {$only}\n");

if ($applyDefaultColors) {
    fwrite(STDOUT, "Colori default: sì\n");
}

if ($colorsJsonPath) {
    fwrite(STDOUT, "File colori: {$colorsJsonPath}\n");
}

$stats = $service->run([
    'dryRun' => $dryRun,
    'only' => $only,
    'applyDefaultColors' => $applyDefaultColors,
    'colorsJsonPath' => $colorsJsonPath,
    'limit' => $limit,
    'forceColor' => $forceColor,
    'verbose' => $verbose,
]);

foreach ($stats['warnings'] as $warning) {
    fwrite(STDOUT, "AVVISO: {$warning}\n");
}

if ($verbose && !empty($stats['log'])) {
    fwrite(STDOUT, "\nDettaglio:\n");

    foreach ($stats['log'] as $line) {
        fwrite(STDOUT, "  {$line}\n");
    }
}

fwrite(STDOUT, "\nRisultati:\n");
fwrite(STDOUT, "  Brand colorati:      {$stats['brandsColored']} (saltati {$stats['brandsSkipped']})\n");
fwrite(STDOUT, "  Calendari aggiornati: {$stats['calendarsUpdated']} (saltati {$stats['calendarsSkipped']})\n");
fwrite(STDOUT, "  Disponibilità:       {$stats['disponibilitaUpdated']} (saltate {$stats['disponibilitaSkipped']})\n");
fwrite(STDOUT, "  Appuntamenti:        {$stats['appuntamentiUpdated']} (saltati {$stats['appuntamentiSkipped']})\n");

if ($dryRun) {
    fwrite(STDOUT, "\nNessuna modifica salvata. Rimuovere --dry-run per applicare.\n");
}
