<?php

use SimpleSAML\Logger;

/*
 Config file to use during integration testing
*/
$config = [
    'baseurlpath' => '/',

    'metadata.sources' => [
        ['type' => 'flatfile', 'directory' =>  dirname(__DIR__) . '/metadata'],
    ],

    'module.enable' => [
        'exampleauth' => true
    ],

    'store.type' => '\CirrusIdentity\SSP\Test\InMemoryStore',

    'debug' => true,
    'logging.level' => Logger::DEBUG,
    'logging.handler' => 'errorlog',

    'secretsalt' =>  'donttellanyone',
    'auth.adminpassword' => 'secret'
];
