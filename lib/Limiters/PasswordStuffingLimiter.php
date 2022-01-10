<?php

namespace SimpleSAML\Module\ratelimit\Limiters;

use SimpleSAML\Assert\Assert;
use SimpleSAML\Configuration;
use SimpleSAML\Utils\Crypto;

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

        Assert::range($this->cost, 4, 31, 'The cost must be an integer between 4 and 31.');
    }


    public function getRateLimitKey(string $username, string $password): string
    {
        return 'password-' . $this->generateSecureKeyFromPassword($password);
    }

    protected function generateSecureKeyFromPassword(string $password): string
    {
        $configUtils = new Utils\Config();

        $salt = $this->determineWindowExpiration(time()) . $configUtils->getSecretSalt();

        // Configure salt to use bcrypt
        $cryptSalt = sprintf('$2y$%02d$', $this->cost) . substr($salt, 0, 22);

        // Generate the bcrypt hash
        $hash = crypt($password, $cryptSalt);

        // Remove the salt, the first two characters of the password hash and then base64-encode the hash
        return base64_encode(substr($hash, strlen($cryptSalt) + 1));
    }
}
