<?php

use SimpleSAML\Logger;

$config['module.enable']['exampleauth'] = true;
$config['module.enable']['ratelimit'] = true;
$config = [
        'enable.saml20-idp' => true,
        'secretsalt' => 'testsalt',
        'logging.level' => Logger::DEBUG,
        'auth.adminpassword' => 'secret',
        'memcache_store.prefix' => 'test',
        'store.type' => 'memcache',
        'memcache_store.servers' => array(
            array(
                array('hostname' => 'memcache-ratelimit', "port" => 11211),
            ),
        ),
    ] + $config;
