<?php
/**
 * Rimuove definizioni duplicate «Crea prodotto» da clientDefs Quote (produzione).
 * Uso: php tools/dedupe-quote-crea-prodotto.php
 */
declare(strict_types=1);

$crmRoot = getenv('CRM_ROOT') ?: (getenv('HOME') ?: '') . '/public_html/crm/mec-group';
$file = $crmRoot . '/custom/Espo/Custom/Resources/metadata/clientDefs/Quote.json';

if (!is_readable($file)) {
    fwrite(STDERR, "File non trovato: {$file}\n");
    exit(1);
}

$data = json_decode((string) file_get_contents($file), true);
if (!is_array($data)) {
    fwrite(STDERR, "JSON non valido\n");
    exit(1);
}

$seen = false;
$filterButtons = static function (array $buttons) use (&$seen): array {
    $out = [];
    foreach ($buttons as $item) {
        if ($item === '__APPEND__') {
            $out[] = $item;
            continue;
        }
        if (!is_array($item)) {
            $out[] = $item;
            continue;
        }
        $name = $item['name'] ?? '';
        if ($name === 'creaProdotto') {
            if ($seen) {
                continue;
            }
            $seen = true;
        }
        $out[] = $item;
    }
    return $out;
};

foreach (['buttonList'] as $key) {
    if (!empty($data[$key]) && is_array($data[$key])) {
        $data[$key] = $filterButtons($data[$key]);
    }
}

foreach (['detail', 'edit'] as $view) {
    if (empty($data['menu'][$view]['buttons']) || !is_array($data['menu'][$view]['buttons'])) {
        continue;
    }
    $data['menu'][$view]['buttons'] = $filterButtons($data['menu'][$view]['buttons']);
}

// Prefer single buttonList; drop duplicate menu entries for creaProdotto
if (!empty($data['menu'])) {
    foreach (['detail', 'edit'] as $view) {
        if (empty($data['menu'][$view]['buttons'])) {
            continue;
        }
        $data['menu'][$view]['buttons'] = array_values(array_filter(
            $data['menu'][$view]['buttons'],
            static function ($item) {
                return $item === '__APPEND__'
                    || !is_array($item)
                    || ($item['name'] ?? '') !== 'creaProdotto';
            }
        ));
        if ($data['menu'][$view]['buttons'] === ['__APPEND__']) {
            unset($data['menu'][$view]['buttons']);
        }
    }
    if (empty($data['menu']['detail']) && empty($data['menu']['edit'])) {
        unset($data['menu']);
    }
}

file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
echo "OK dedupe Quote.json — un solo creaProdotto in buttonList\n";
