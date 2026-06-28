<?php
/**
 * Alias — inventario solo hook (vedi audit-custom-server.php --hooks-only).
 * Uso: php tools/audit-custom-hooks.php
 */

declare(strict_types=1);

$script = __DIR__ . '/audit-custom-server.php';
passthru('php ' . escapeshellarg($script) . ' --hooks-only', $code);
exit($code);
