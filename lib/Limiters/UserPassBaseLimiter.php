<?php

namespace SimpleSAML\Module\ratelimit\Limiters;

use SimpleSAML\Assert\Assert;
use SimpleSAML\Configuration;
use SimpleSAML\Store\StoreFactory;
use SimpleSAML\Utils\Time;

abstract class UserPassBaseLimiter implements UserPassLimiter
{
    protected const PREAUTH_ALLOW = 'allow';
    protected const PREAUTH_BLOCK = 'block';
    protected const PREAUTH_CONTINUE = 'continue';

    /**
     * @var int $limit The limit of attempts
     */
    protected int $limit;

    /**
     * @var int $window The time window in seconds to count attempts
     */
    protected int $window;

    /**
     * UserPassBaseLimiter constructor.
     */
    public function __construct(Configuration $config)
    {
        $timeUtils = new Time();

        $windowDuration = $config->getString('window', 'PT5M');
        $this->window = $timeUtils->parseDuration($windowDuration, 0);

        // If window is negative than misconfiguration
        Assert::positiveInteger(
            $this->window,
            'Invalid duration \'' . $this->window . '\'. Defaulting to 5m'
        );

        $this->limit = $config->getInteger('limit', 15);
    }


    /**
     * Called prior to verifying the credentials to determine if the attempt is allowed.
     * @param string $username The username to check
     * @param string $password The password to check
     * @return string allow|block|continue
     */
    public function allow(string $username, string $password): string
    {
        $key = $this->getRateLimitKey($username, $password);
        $count = $this->getCurrentCount($key);
        if ($count >= $this->limit) {
            return UserPassBaseLimiter::PREAUTH_BLOCK;
        }
        return UserPassBaseLimiter::PREAUTH_CONTINUE;
    }

    /**
     * Called after a successful authentication
     * @param string $username The username to check
     * @param string $password The password to check
     */
    public function postSuccess(string $username, string $password): void
    {
        // For most limiters success is a noop.
        return;
    }

    /**
     * Called after a failed authentication
     * @param string $username The username to check
     * @param string $password The password to check
     * @return int The current failure count for this limit
     */
    public function postFailure(string $username, string $password): int
    {
        $key = $this->getRateLimitKey($username, $password);
        $expiration = $this->determineWindowExpiration(time());
        $store = StoreFactory::getInstance();
        $count = $this->getCurrentCount($key) + 1;
        $store->set('int', "ratelimit-$key", $count, $expiration);
        return $count;
    }

    /**
     * Relaxed visibility for testing
     * @param int $time The curent time to use for calculating
     * @return int The expiration date for this limit window
     */
    public function determineWindowExpiration(int $time): int
    {
        return ceil(($time + 1) / $this->window) * $this->window;
    }

    /**
     * Get the current number of failed authentication attempts
     * @param string $key The key the attempts are being tracked under
     * @return int The number of failed attempts.
     */
    protected function getCurrentCount(string $key): int
    {
        $store = StoreFactory::getInstance();
        $count = $store->get('int', "ratelimit-$key");
        return $count ?? 0;
    }

    abstract public function getRateLimitKey(string $username, string $password): string;
}
