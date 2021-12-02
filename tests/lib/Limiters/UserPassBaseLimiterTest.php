<?php

namespace SimpleSAML\Test\Module\ratelimit\Limiters;

use SimpleSAML\Configuration;
use SimpleSAML\Module\ratelimit\Limiters\UserPassBaseLimiter;

class UserPassBaseLimiterTest extends BaseLimitTest
{

    protected function getLimiter(array $config): UserPassBaseLimiter
    {
        return new SampleLimiter(Configuration::loadFromArray($config));
    }

    /**
     * Confirm that the key changes with different inputs.
     */
    public function testKeyVariesWithInput(): void
    {
        $limiter = $this->getLimiter([]);
        $key1 = $limiter->getRateLimitKey('1', 'p');
        $key2 = $limiter->getRateLimitKey('2', 'p');
        $this->assertNotEquals($key1, $key2, 'keys should vary');
    }
}

class SampleLimiter extends UserPassBaseLimiter
{

    public function getRateLimitKey(string $username, string $password): string
    {
        return 'sample-' . $username[0];
    }

    public function determineWindowExpiration(int $time): int
    {
        return parent::determineWindowExpiration($time);
    }
}
