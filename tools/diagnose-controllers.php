#!/usr/bin/env php
<?php
/**
 * Diagnostica errori 500 CRM — trova controller/classi custom che non caricano.
 *
 *   cd ~/public_html/crm/mec-group
 *   php tools/diagnose-controllers.php
 */

declare(strict_types=1);

$root = getenv('CRM_ROOT') ?: getcwd();
$customRoot = $root . '/custom/Espo/Custom';

echo "=== Diagnostica controller custom EspoCRM ===\n\n";

require_once $root . '/vendor/autoload.php';

$dirs = [
    'Controllers' => 'Espo\\Custom\\Controllers\\',
];

$failed = 0;

foreach ($dirs as $subdir => $namespace) {
    $path = $customRoot . '/' . $subdir;

    if (!is_dir($path)) {
        echo "[WARN] Cartella mancante: {$subdir}\n";
        continue;
    }

    $files = glob($path . '/*.php') ?: [];

    foreach ($files as $file) {
        $base = basename($file, '.php');
        $class = $namespace . $base;

        echo "Test {$class} ... ";

        try {
            if (!class_exists($class)) {
                throw new RuntimeException('class_exists=false dopo autoload');
            }

            $ref = new ReflectionClass($class);
            $parent = $ref->getParentClass();
            $parentName = $parent ? $parent->getName() : '(nessuno)';

            if ($parent && !class_exists($parent->getName())) {
                throw new RuntimeException('parent mancante: ' . $parent->getName());
            }

            $content = file_get_contents($file) ?: '';

            if (str_contains($content, 'Espo\\Core\\Controllers\\Base')) {
                throw new RuntimeException('usa Espo\\Core\\Controllers\\Base (rimosso in Espo 10)');
            }

            echo "OK (extends {$parentName})\n";
        } catch (Throwable $e) {
            $failed++;
            echo "ERR\n";
            echo "      {$e->getMessage()}\n";
            echo "      File: {$file}\n";
        }
    }
}

echo "\n";

if ($failed > 0) {
    echo "Controller con errori: {$failed}\n";
    echo "Correggere i file ERR poi:\n";
    echo "  php clear_cache.php\n";
    echo "  rm -rf data/cache/*\n";

    if (function_exists('opcache_reset')) {
        echo "  php -r \"opcache_reset();\"\n";
    }

    exit(1);
}

echo "Tutti i controller OK.\n";
echo "Se il CRM è ancora giù:\n";
echo "  tail -50 data/logs/espo-" . date('Y-m-d') . ".log | grep ERROR\n";
echo "  rm -rf data/cache/* && php clear_cache.php\n";

exit(0);
