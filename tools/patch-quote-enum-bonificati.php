<?php
/**
 * Patch IN-PLACE: riscrive SOLO gli enum bonificati su Quote.json + i18n.
 * Non scarica/sovrascrive il file intero (evita regressioni da branch sporchi).
 *
 *   php tools/patch-quote-enum-bonificati.php
 */

declare(strict_types=1);

$root = getenv('CRM_ROOT') ?: (isset($argv[1]) ? $argv[1] : getcwd());
$root = rtrim($root, '/');

$metaPath = $root . '/custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json';
$i18nPath = $root . '/custom/Espo/Custom/Resources/i18n/it_IT/Quote.json';

$expectedStatus = ['Bozza', 'In Gestione', 'Appuntamento fissato', 'Installato', 'Invalido'];
$expectedContratto = ['', 'Inserito', 'In lavorazione', 'Chiuso', 'Sospeso', 'Annullato', 'Recesso'];
$expectedFin = ['', 'In valutazione', 'In attesa OTP', 'Approvato', 'In rivalutazione', 'In attesa di documentazione', 'Respinto', 'Annullato'];

$styleStatus = [
    'Bozza' => 'default',
    'In Gestione' => 'primary',
    'Appuntamento fissato' => 'info',
    'Installato' => 'success',
    'Invalido' => 'danger',
];
$styleContratto = [
    '' => null,
    'Inserito' => null,
    'In lavorazione' => null,
    'Chiuso' => 'success',
    'Sospeso' => null,
    'Annullato' => null,
    'Recesso' => null,
];
$styleFin = [
    '' => null,
    'In valutazione' => 'primary',
    'In attesa OTP' => 'info',
    'Approvato' => 'success',
    'In rivalutazione' => null,
    'In attesa di documentazione' => 'warning',
    'Respinto' => 'danger',
    'Annullato' => null,
];

$i18nFin = [
    'In attesa OTP' => 'In attesa OTP',
    'In valutazione' => 'In valutazione',
    'In rivalutazione' => 'In rivalutazione',
    'In attesa di documentazione' => 'In attesa di documentazione',
    'Approvato' => 'Approvato',
    'Respinto' => 'Respinto',
    'Annullato' => 'Annullato',
    '' => '',
];
$i18nContratto = [
    'In lavorazione' => 'In lavorazione',
    'Annullato' => 'Annullato',
    '' => '',
    'Sospeso' => 'Sospeso',
    'Inserito' => 'Inserito',
    'Recesso' => 'Recesso',
    'Chiuso' => 'Chiuso',
];
$i18nStatus = [
    'Bozza' => 'Bozza',
    'In Gestione' => 'In Gestione',
    'Installato' => 'Installato',
    'Invalido' => 'Invalido',
    'Appuntamento fissato' => 'Appuntamento fissato',
];

function loadJson(string $path): array
{
    if (!is_file($path)) {
        fwrite(STDERR, "ERR file mancante: {$path}\n");
        exit(1);
    }

    $raw = file_get_contents($path);
    $data = json_decode((string) $raw, true);

    if (!is_array($data)) {
        fwrite(STDERR, "ERR JSON non valido: {$path}\n");
        exit(1);
    }

    return $data;
}

function saveJson(string $path, array $data): void
{
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if ($json === false) {
        fwrite(STDERR, "ERR encode: {$path}\n");
        exit(1);
    }

    // Espo metadata tipicamente usa indent 4
    $json = preg_replace('/^(  +)/m', '$0$0', $json) ?? $json;
    file_put_contents($path, $json . "\n");
}

$meta = loadJson($metaPath);
$meta['fields']['status']['options'] = $expectedStatus;
$meta['fields']['status']['style'] = $styleStatus;
$meta['fields']['statoContratto']['options'] = $expectedContratto;
$meta['fields']['statoContratto']['style'] = $styleContratto;
$meta['fields']['statoContratto']['default'] = 'Inserito';
$meta['fields']['statoFinanziamento']['options'] = $expectedFin;
$meta['fields']['statoFinanziamento']['style'] = $styleFin;
$meta['fields']['statoFinanziamento']['default'] = null;
saveJson($metaPath, $meta);
echo "[OK] patch enum metadata Quote.json\n";

$i18n = loadJson($i18nPath);
$i18n['options']['status'] = $i18nStatus;
$i18n['options']['statoContratto'] = $i18nContratto;
$i18n['options']['statoFinanziamento'] = $i18nFin;
saveJson($i18nPath, $i18n);
echo "[OK] patch enum i18n Quote.json\n";

echo "Fatto. Lancia: php clear_cache.php && rm -rf data/cache/* && php rebuild.php\n";
