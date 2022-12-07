<?php

$config = array(

    // This is a authentication source which handles admin authentication.
    'admin' => array(
        'ratelimit:RateLimitUserPass',
        'delegate' => [
            'core:AdminPassword',
        ]
    ),

    'example-userpass' => [
        'ratelimit:RateLimitUserPass',
        'delegate' => 'example-noratelimit',
        // These limits are much lower than you want for a production system. They are low to make testing easy
        'ratelimit' => [
            0 => [
                'device',
                'window' => 'P28D',
                'limit' => 10,
            ],
            1 => [
                'user',
                'window' => 'PT5M',
                'limit' => 2
            ],
            2 => [
                'password',
                'window' => 'PT5M',
                'limit' => 3
            ],
            3 => [
                'ip',
                'window' => 'PT5M',
                'limit' => 12,
                'whitelist' => [
                    '1.2.3.4',
                    '5.6.7.0/24',
                ],
            ],
        ]
    ],
    'example-noratelimit' => [
        'exampleauth:UserPass',
        'student:studentpass' => [
            'uid' => ['student'],
            'eduPersonAffiliation' => ['member', 'student'],
        ],
        'employee:employeepass' => [
            'uid' => ['employee'],
            'eduPersonAffiliation' => ['member', 'employee'],
            'eduPersonEntitlement' => ['urn:example:oidc:manage:client']
        ],
        'member:memberpass' => [
            'uid' => ['member'],
            'eduPersonAffiliation' => ['member'],
            'eduPersonEntitlement' => ['urn:example:oidc:manage:client']
        ],
        'minimal:minimalpass' => [
            'uid' => ['minimal'],
        ],
    ],

    'loop-test' => [
        'saml:SP',
        'entityID' => 'https://ratelimit.local.stack-dev.cirrusidentity.com/simplesaml/module.php/saml/sp/metadata.php/loop-test',
        'idp' => 'https://ratelimit.local.stack-dev.cirrusidentity.com/simplesaml/saml2/idp/metadata.php'
    ]
);
