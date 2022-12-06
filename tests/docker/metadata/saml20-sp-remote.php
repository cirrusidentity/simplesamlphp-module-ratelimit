<?php

$metadata['https://ratelimit.local.stack-dev.cirrusidentity.com/simplesaml/module.php/saml/sp/metadata.php/loop-test'] = [
    'SingleLogoutService' => [
        [
            'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect',
            'Location' => 'https://ratelimit.local.stack-dev.cirrusidentity.com/simplesaml/module.php/saml/sp/saml2-logout.php/loop-test',
        ],
    ],
    'AssertionConsumerService' => [
        [
            'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST',
            'Location' => 'https://ratelimit.local.stack-dev.cirrusidentity.com/simplesaml/module.php/saml/sp/saml2-acs.php/loop-test',
            'index' => 0,
        ],
        [
            'Binding' => 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Artifact',
            'Location' => 'https://ratelimit.local.stack-dev.cirrusidentity.com/simplesaml/module.php/saml/sp/saml2-acs.php/loop-test',
            'index' => 1,
        ],
    ],
];
