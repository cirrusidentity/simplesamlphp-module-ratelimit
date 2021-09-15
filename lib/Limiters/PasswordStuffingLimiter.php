<?php

namespace SimpleSAML\Module\ratelimit\Limiters;

use SAML2\Utils;
use SimpleSAML\Configuration;
use SimpleSAML\Utils\Config;

/**
 * Prevent password stuffing attacks by blocking repeated attempts on an incorrect password.
 * @package SimpleSAML\Module\ratelimit\Limiters
 */
class PasswordStuffingLimiter extends UserPassBaseLimiter
{
   /**
     * @var int The cost to use with bcrypt
     */
    private $cost;

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

    protected function generateSecureKeyFromPassword(string $password)
    {
        $configUtils = new Config();

        $salt = '' . $this->determineWindowExpiration(time()) . $configUtils->getSecretSalt();
        // Configure salt to use bcrypt
        $cryptSalt = sprintf('$2a$%02d$', $this->cost) . $salt;
        $hash = crypt($password, $cryptSalt);
        // Remove the salt, and only use part of the hash out of paranoia
        $key = substr($hash, strlen($cryptSalt), 25);
        return $key;
    }
}
