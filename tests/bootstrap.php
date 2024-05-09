<?php

declare(strict_types=1);

putenv('SIMPLESAMLPHP_CONFIG_DIR=' . __DIR__ . '/config');

$projectRoot = dirname(__DIR__);
$projectConfigDirectory = $projectRoot . '/tests/config';

/** @psalm-suppress UnresolvableInclude */
require_once($projectRoot . '/vendor/autoload.php');

// Symlink module into ssp vendor lib so that templates and urls can resolve correctly
$linkPath = $projectRoot . '/vendor/simplesamlphp/simplesamlphp/modules/ratelimit';
if (file_exists($linkPath) === false) {
    echo "Linking '$linkPath' to '$projectRoot'\n";
    symlink($projectRoot, $linkPath);
}

// Symlink configuration into ssp vendor lib so that config can be resolved correctly
$linkPath = $projectRoot . '/vendor/simplesamlphp/simplesamlphp/config';
if (is_link($linkPath) === false) {
    @rename($linkPath, $linkPath . '-preinstalled');
    echo "Linking '$projectConfigDirectory' to '$linkPath'\n";
    symlink($projectConfigDirectory, $linkPath);
}
