#!/usr/bin/env php
<?php
/**
 * Compat wrapper: inoltra al framework unico anti-regressione.
 *
 *   php tools/verify-quote-stati-deploy.php
 */

declare(strict_types=1);

$root = getenv('CRM_ROOT') ?: getcwd();
$cmd = sprintf(
    'php %s --profile=quote-stati --root=%s',
    escapeshellarg(__DIR__ . '/verify-regressions.php'),
    escapeshellarg($root)
);

passthru($cmd, $code);
exit($code);
