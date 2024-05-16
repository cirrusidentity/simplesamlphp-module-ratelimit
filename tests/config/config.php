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
        'exampleauth' => true,
        'ratelimit' => true,
    ],

    'store.type' => '\SimpleSAML\TestUtils\InMemoryStore',

    'debug' => true,
    'logging.level' => Logger::DEBUG,
    'logging.handler' => 'errorlog',

    'secretsalt' =>  'donttellanyone',
    //'secretsalt' =>  '!',
    'auth.adminpassword' => 'secret',
    'tempdir' => sys_get_temp_dir(),
    'loggingdir'  => sys_get_temp_dir(),
];
