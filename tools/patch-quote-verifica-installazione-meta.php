<?php
/**
 * Patch IN-PLACE: aggiunge solo campi/link verifica installazione su Quote
 * senza sostituire il file intero (non tocca gli enum).
 *
 *   php tools/patch-quote-verifica-installazione-meta.php
 */

declare(strict_types=1);

$root = getenv('CRM_ROOT') ?: (isset($argv[1]) ? $argv[1] : getcwd());
$root = rtrim($root, '/');

$metaPath = $root . '/custom/Espo/Custom/Resources/metadata/entityDefs/Quote.json';
$i18nPath = $root . '/custom/Espo/Custom/Resources/i18n/it_IT/Quote.json';

function loadJson(string $path): array
{
    if (!is_file($path)) {
        fwrite(STDERR, "ERR file mancante: {$path}\n");
        exit(1);
    }

    $data = json_decode((string) file_get_contents($path), true);

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

    $json = preg_replace('/^(  +)/m', '$0$0', $json) ?? $json;
    file_put_contents($path, $json . "\n");
}

$meta = loadJson($metaPath);

$meta['fields']['verificaInstallazioneTask'] = [
    'type' => 'link',
    'entity' => 'Task',
    'readOnly' => true,
    'isCustom' => true,
];

$meta['links']['verificaInstallazioneTask'] = [
    'type' => 'belongsTo',
    'entity' => 'Task',
    'foreign' => 'quotesVerificaInstallazione',
    'isCustom' => true,
];

saveJson($metaPath, $meta);
echo "[OK] patch field/link verificaInstallazioneTask\n";

$i18n = loadJson($i18nPath);
$i18n['fields']['verificaInstallazioneTask'] = 'To-Do verifica installazione';
$i18n['links']['verificaInstallazioneTask'] = 'To-Do verifica installazione';
saveJson($i18nPath, $i18n);
echo "[OK] patch i18n verificaInstallazioneTask\n";
