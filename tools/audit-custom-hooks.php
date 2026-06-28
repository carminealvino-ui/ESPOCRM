<?php
/**
 * Elenca tutti gli hook custom (file .php in Hooks/).
 * Uso: php tools/audit-custom-hooks.php
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$hooksRoot = $root . '/custom/Espo/Custom/Hooks';

if (!is_dir($hooksRoot)) {
    fwrite(STDERR, "Cartella non trovata: {$hooksRoot}\n");
    exit(1);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($hooksRoot, FilesystemIterator::SKIP_DOTS)
);

$rows = [];

foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    $relative = substr($path, strlen($root) + 1);
    $entity = basename(dirname($path));

    if ($entity === 'Hooks') {
        continue;
    }

    $rows[] = [
        'entity' => $entity,
        'file' => basename($path),
        'path' => $relative,
        'bytes' => filesize($path),
        'mtime' => date('Y-m-d H:i', filemtime($path)),
    ];
}

usort($rows, static function (array $a, array $b): int {
    return [$a['entity'], $a['file']] <=> [$b['entity'], $b['file']];
});

printf("%-18s %-40s %8s %s\n", 'ENTITÀ', 'FILE', 'BYTES', 'MODIFICATO');
printf("%s\n", str_repeat('-', 90));

foreach ($rows as $row) {
    printf(
        "%-18s %-40s %8d %s\n",
        $row['entity'],
        $row['file'],
        $row['bytes'],
        $row['mtime']
    );
}

echo "\nTotale hook: " . count($rows) . "\n";
echo "Vedi REGOLE-PRODUZIONE/REGOLE.md sezione 11 — rimuovere file non più necessari.\n";
