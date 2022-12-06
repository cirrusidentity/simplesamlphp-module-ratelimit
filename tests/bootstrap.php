<?php

putenv('SIMPLESAMLPHP_CONFIG_DIR=' . __DIR__ . '/config');

$projectRootDirectory = dirname(__DIR__);
$projectConfigDirectory = $projectRootDirectory . '/tests/config';
$modulePath = $projectRootDirectory . '/vendor/simplesamlphp/simplesamlphp/modules/ratelimit';
$simplesamlphpConfig = $projectRootDirectory . '/vendor/simplesamlphp/simplesamlphp/config';

/** @psalm-suppress UnresolvableInclude */
require_once($projectRootDirectory . '/vendor/autoload.php');

/**
 * Sets a link in the simplesamlphp vendor directory
 * @param string $target
 * @param string $link
 * @return void
 */
function symlinkModulePathInVendorDirectory($target, $link)
{
    if (file_exists($link) === false) {
        // If the link is invalid, remove it.
        if (is_link($link)) {
            unlink($link);
        }
        print "Linking '$link' to '$target'\n";
        symlink($target, $link);
    } else {
        if (is_link($link) === false) {
            // Looks like there is a directory here. Lets remove it and symlink in this one
            print "Renaming pre-installed path and linking '$link' to '$target'\n";
            rename($link, $link . '-preinstalled');
            symlink($target, $link);
        }
    }
}

symlinkModulePathInVendorDirectory($projectRootDirectory, $modulePath);
symlinkModulePathInVendorDirectory($projectConfigDirectory, $simplesamlphpConfig);
