<?php

/**
 * SAML 2.0 IdP configuration for SimpleSAMLphp.
 *
 * See: https://simplesamlphp.org/docs/stable/simplesamlphp-reference-idp-hosted
 */

$metadata['https://ratelimit.local.stack-dev.cirrusidentity.com/simplesaml/saml2/idp/metadata.php'] = [
    /*
     * The hostname of the server (VHOST) that will use this SAML entity.
     *
     * Can be '__DEFAULT__', to use this entry by default.
     */
    'host' => '__DEFAULT__',

    // X.509 key and certificate. Relative to the cert directory.
    'privatekey' => 'server.pem',
    'certificate' => 'server.crt',

    /*
     * Authentication source to use. Must be one that is configured in
     * 'config/authsources.php'.
     */
    'auth' => 'example-userpass',

    'attributes.NameFormat' => 'urn:oasis:names:tc:SAML:2.0:attrname-format:uri',
    'authproc' => [
        // Stop loops
        100 => [
            'class' => 'ratelimit:LoopDetection',
            'secondsSinceLastSso' => 10,
            'loopsBeforeWarning' => 4,
            'logOnly' => false
        ],
    ],

];
