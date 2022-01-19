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
        'delegate' => [
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
        ]
    ],
    'loop-test' => [
        'saml:SP',
        'idp' => 'https://ratelimit.local.stack-dev.cirrusidentity.com/simplesaml/saml2/idp/metadata.php'
    ]
);
