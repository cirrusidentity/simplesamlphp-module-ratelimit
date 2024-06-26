<?php

declare(strict_types=1);

namespace SimpleSAML\Module\ratelimit\Limiters;

use SimpleSAML\Assert\Assert;
use SimpleSAML\Configuration;
use SimpleSAML\Module\ratelimit\PreAuthStatusEnum;
use SimpleSAML\Store\{StoreFactory, StoreInterface};
use SimpleSAML\Utils\Time;

use function ceil;
use function intval;

abstract class UserPassBaseLimiter implements UserPassLimiter
{
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
    public function __construct(Configuration $config, string $defaultWindow = 'PT5M')
    {
        $timeUtils = new Time();
        $windowDuration = $config->getOptionalString('window', $defaultWindow);
        $this->window = $timeUtils->parseDuration($windowDuration, 0);

        // If window is negative than misconfiguration
        Assert::positiveInteger(
            $this->window,
            'Invalid duration \'' . $this->window . '\'. Defaulting to 5m'
        );
        $this->limit = $config->getOptionalInteger('limit', 15);
    }

    /**
     * Called prior to verifying the credentials to determine if the attempt is allowed.
     * @param string $username The username to check
     * @param string $password The password to check
     * @return \SimpleSAML\Module\ratelimit\PreAuthStatusEnum
     */
    public function allow(string $username, string $password): PreAuthStatusEnum
    {
        $key = $this->getRateLimitKey($username, $password);
        $count = $this->getCurrentCount($key);
        if ($count >= $this->limit) {
            return PreAuthStatusEnum::BLOCK;
        }
        return PreAuthStatusEnum::CONTINUE;
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
        $count = $this->getCurrentCount($key) + 1;
        $this->getStore()->set('int', "ratelimit-$key", $count, $expiration);
        return $count;
    }

    /**
     * Relaxed visibility for testing
     * @param int $time The current time to use for calculating
     * @return int The expiration date for this limit window
     */
    public function determineWindowExpiration(int $time): int
    {
        return intval(ceil(($time + 1) / $this->window)) * $this->window;
    }

    /**
     * Get the current number of failed authentication attempts
     * @param string $key The key the attempts are being tracked under
     * @return int The number of failed attempts.
     */
    protected function getCurrentCount(string $key): int
    {
        /** @var int|null $count */
        $count = $this->getStore()->get('int', "ratelimit-$key");
        return $count ?? 0;
    }

    public function getStore(): StoreInterface
    {
        $config = Configuration::getInstance();
        $storeType = $config->getOptionalString('store.type', 'phpsession');
        $store = StoreFactory::getInstance($storeType);
        Assert::notFalse($store, "Store must be configured");
        /** @psalm-var \SimpleSAML\Store\StoreInterface $store */
        return $store;
    }

    abstract public function getRateLimitKey(string $username, string $password): string;
}
