<?php
/**
 * Inventario file custom sul server (tutto custom rilevante per EspoCRM).
 *
 * Uso:
 *   php tools/audit-custom-server.php
 *   php tools/audit-custom-server.php --hooks-only
 *   php tools/audit-custom-server.php --json > exports/audit-custom-$(date +%Y%m%d).json
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$hooksOnly = in_array('--hooks-only', $argv, true);
$asJson = in_array('--json', $argv, true);

$scanRoots = [
    'Hooks' => $root . '/custom/Espo/Custom/Hooks',
    'Actions' => $root . '/custom/Espo/Custom/Actions',
    'Services' => $root . '/custom/Espo/Custom/Services',
    'Controllers' => $root . '/custom/Espo/Custom/Controllers',
    'Repositories' => $root . '/custom/Espo/Custom/Repositories',
    'Entities' => $root . '/custom/Espo/Custom/Entities',
    'Resources' => $root . '/custom/Espo/Custom/Resources',
    'client/custom' => $root . '/client/custom',
    'Sales/Hooks' => $root . '/custom/Espo/Modules/Sales/Hooks',
    'Sales/Classes' => $root . '/custom/Espo/Modules/Sales/Classes',
    'Sales/Resources' => $root . '/custom/Espo/Modules/Sales/Resources',
];

$skipDirNames = [
    'backup-layouts',
    'backup_dev',
    'node_modules',
    'vendor',
    'cache',
];

$allowedExtensions = ['php', 'json', 'js', 'css'];

$rows = [];

foreach ($scanRoots as $label => $dir) {
    if ($hooksOnly && $label !== 'Hooks') {
        continue;
    }

    if (!is_dir($dir)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }

        $pathname = $file->getPathname();

        foreach ($skipDirNames as $skip) {
            if (str_contains($pathname, '/' . $skip . '/')) {
                continue 2;
            }
        }

        $ext = strtolower($file->getExtension());

        if (!in_array($ext, $allowedExtensions, true)) {
            continue;
        }

        if (str_ends_with($file->getFilename(), '.bak')) {
            continue;
        }

        $relative = substr($pathname, strlen($root) + 1);
        $entity = '';

        if ($label === 'Hooks') {
            $entity = basename(dirname($pathname));
            if ($entity === 'Hooks') {
                $entity = '-';
            }
        }

        $rows[] = [
            'area' => $label,
            'entity' => $entity,
            'file' => $file->getFilename(),
            'path' => $relative,
            'bytes' => $file->getSize(),
            'mtime' => date('Y-m-d H:i', $file->getMTime()),
            'ext' => $ext,
        ];
    }
}

usort($rows, static function (array $a, array $b): int {
    return [$a['area'], $a['entity'], $a['path']] <=> [$b['area'], $b['entity'], $b['path']];
});

if ($asJson) {
    echo json_encode([
        'generatedAt' => date('c'),
        'crmRoot' => $root,
        'total' => count($rows),
        'files' => $rows,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    exit(0);
}

$byArea = [];

foreach ($rows as $row) {
    $byArea[$row['area']] = ($byArea[$row['area']] ?? 0) + 1;
}

echo "=== Audit custom server ===\n";
echo "Root: {$root}\n\n";

foreach ($scanRoots as $label => $dir) {
    if ($hooksOnly && $label !== 'Hooks') {
        continue;
    }

    $count = $byArea[$label] ?? 0;
    $status = is_dir($dir) ? (string) $count . ' file' : 'assente';
    printf("  %-18s %s\n", $label . ':', $status);
}

echo "\n";

if ($hooksOnly) {
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
} else {
    printf("%-16s %-10s %-50s %8s %s\n", 'AREA', 'ENTITÀ', 'PERCORSO', 'BYTES', 'MODIFICATO');
    printf("%s\n", str_repeat('-', 100));

    foreach ($rows as $row) {
        printf(
            "%-16s %-10s %-50s %8d %s\n",
            $row['area'],
            $row['entity'] ?: '-',
            $row['path'],
            $row['bytes'],
            $row['mtime']
        );
    }
}

echo "\nTotale file: " . count($rows) . "\n";
echo "Vedi REGOLE-PRODUZIONE/REGOLE.md §11–§12 — rimuovere file obsoleti in tutto custom.\n";
