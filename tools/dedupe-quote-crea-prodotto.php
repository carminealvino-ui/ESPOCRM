<?php
/**
 * Rimuove solo duplicati «creaProdotto*» dentro ogni singola lista (non cancella menu vs buttonList).
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

$isCreaProdotto = static function ($item): bool {
    if (!is_array($item)) {
        return false;
    }
    $name = (string) ($item['name'] ?? '');

    return str_starts_with($name, 'creaProdotto');
};

$dedupeList = static function (array $buttons) use ($isCreaProdotto): array {
    $seen = false;
    $out = [];
    foreach ($buttons as $item) {
        if (is_array($item) && $isCreaProdotto($item)) {
            if ($seen) {
                continue;
            }
            $seen = true;
        }
        $out[] = $item;
    }

    return $out;
};

if (!empty($data['buttonList']) && is_array($data['buttonList'])) {
    $data['buttonList'] = $dedupeList($data['buttonList']);
}

if (!empty($data['detailActionList']) && is_array($data['detailActionList'])) {
    $data['detailActionList'] = $dedupeList($data['detailActionList']);
}

// Rimuovi pulsante da testata (solo Articoli)
$stripCrea = static function (array $buttons) use ($isCreaProdotto): array {
    return array_values(array_filter($buttons, static function ($item) use ($isCreaProdotto) {
        return $item === '__APPEND__' || !is_array($item) || !$isCreaProdotto($item);
    }));
};

if (!empty($data['menu']) && is_array($data['menu'])) {
    foreach (['detail', 'edit'] as $view) {
        if (empty($data['menu'][$view]['buttons']) || !is_array($data['menu'][$view]['buttons'])) {
            continue;
        }
        $data['menu'][$view]['buttons'] = $stripCrea($data['menu'][$view]['buttons']);
        if ($data['menu'][$view]['buttons'] === ['__APPEND__']) {
            unset($data['menu'][$view]['buttons']);
        }
    }
    if (isset($data['menu']['detail'], $data['menu']['edit']) &&
        empty($data['menu']['detail']) && empty($data['menu']['edit'])) {
        unset($data['menu']);
    } elseif (isset($data['menu']['detail']) && $data['menu']['detail'] === [] &&
        isset($data['menu']['edit']) && $data['menu']['edit'] === []) {
        unset($data['menu']);
    }
}

if (!empty($data['detailActionList']) && is_array($data['detailActionList'])) {
    $data['detailActionList'] = $stripCrea($data['detailActionList']);
    if ($data['detailActionList'] === ['__APPEND__']) {
        unset($data['detailActionList']);
    }
}

if (!empty($data['buttonList']) && is_array($data['buttonList'])) {
    $data['buttonList'] = $stripCrea($data['buttonList']);
    if ($data['buttonList'] === ['__APPEND__']) {
        unset($data['buttonList']);
    }
}

file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
echo "OK dedupe Quote.json (solo duplicati nella stessa lista)\n";
