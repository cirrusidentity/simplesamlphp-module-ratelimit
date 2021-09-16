<?php

namespace SimpleSAML\Module\ratelimit\Limiters;

use SAML2\Utils;
use SimpleSAML\Configuration;
use SimpleSAML\Utils;

/**
 * Prevent password stuffing attacks by blocking repeated attempts on an incorrect password.
 * @package SimpleSAML\Module\ratelimit\Limiters
 */
class PasswordStuffingLimiter extends UserPassBaseLimiter
{
   /**
     * @var int The cost to use with bcrypt
     */
    private int $cost;

    public function __construct(Configuration $config)
    {
        parent::__construct($config);
        // We aren't storing the whole hash or for very long, so favor speed
        $this->cost = $config->getInteger('hashCost', 8);
    }


    public function getRateLimitKey(string $username, string $password): string
    {

        return 'password-' . $this->generateSecureKeyFromPassword($password);
    }

    protected function generateSecureKeyFromPassword(string $password): string
    {
        $cryptoUtils = new Utils\Crypto();
        return base64_encode($cryptoUtils->pwHash($password));
    }
}
