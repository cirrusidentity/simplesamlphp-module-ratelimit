<?php

namespace SimpleSAML\Module\ratelimit\Limiters;

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
        $configUtils = new Config();
        //TODO: need to make sure this only contains legal salt characters
        $salt = '' . $this->determineWindowExpiration(time()) . $configUtils->getSecretSalt();
        // Configure salt to use bcrypt
        $cryptSalt = sprintf('$2a$%02d$', $this->cost) . $salt;
        $hash = crypt($password, $cryptSalt);
        if (empty($hash)) {
            throw new \RuntimeException('Unable to generate password hash key');
        }
        echo "hash $hash";
        // Remove the salt since deployers may not want the secret salt being saved in the store
        $key = substr($hash, strlen($cryptSalt));
        // encode the $key in case it contains special characters not compatible with the store
        return $hash;
//        echo "salt $salt\t";
//
//        // salt is deprecated in password_hash
//        $result = password_hash($password, PASSWORD_BCRYPT, ['salt' => $salt, 'cost' => $this->cost]);
//        if (is_null($result) || $result === false) {
//            throw new \RuntimeException('Unable to generate password hash key');
//        }
//        return $result;
    }
}
