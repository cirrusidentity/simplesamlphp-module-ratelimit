<?php

declare(strict_types=1);

namespace SimpleSAML\Module\ratelimit\Limiters;

use RuntimeException;
use SimpleSAML\Assert\Assert;
use SimpleSAML\{Configuration, Logger};
use SimpleSAML\Utils\Config;

use function crypt;
use function sprintf;
use function strlen;
use function substr;
use function time;

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
        $this->cost = $config->getOptionalInteger('hashCost', 8);

        Assert::range($this->cost, 4, 31, 'The cost must be an integer between 4 and 31.');
    }


    public function getRateLimitKey(string $username, string $password): string
    {
        return 'password-' . $this->generateSecureKeyFromPassword($password);
    }

    protected function generateSecureKeyFromPassword(string $password): string
    {
        $configUtils = new Config();
        $configSalt = $configUtils->getSecretSalt();
        if (strlen($configSalt) < 12) {
            Logger::warning('ratelimit: secretsalt needs to be at least 12 characters');
        }
        $salt = $this->determineWindowExpiration(time()) . $configSalt;

        // Configure salt to use bcrypt. bcrypt supports salt up to 22 characters
        $cryptSalt = sprintf('$2y$%02d$', $this->cost) . substr($salt, 0, 22);

        // Generate the bcrypt hash. We opt not to use password_hash since we want to control the salt.
        $hash = crypt($password, $cryptSalt);

        /**
         * On crypt failure it returns 'a string that is shorter than 13 characters and is guaranteed to differ from
         * the salt on failure.'. Failure reasons can include a salt with special characters or a small salt.
         */
        if (strlen($hash) < 13) {
            throw new RuntimeException('Unable to generate password hash key');
        }


        // Remove the salt, the first two characters of the password hash and then base64-encode the hash
        return base64_encode(substr($hash, strlen($cryptSalt) + 1));
    }
}
