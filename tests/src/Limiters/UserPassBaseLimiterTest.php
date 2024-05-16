<?php

declare(strict_types=1);

namespace SimpleSAML\Test\Module\ratelimit\Limiters;

use PHPUnit\Framework\Attributes\CoversClass;
use SimpleSAML\Configuration;
use SimpleSAML\Module\ratelimit\Limiters\UserPassBaseLimiter;
use SimpleSAML\Test\Module\ratelimit\Utils\BaseLimitTest;
use SimpleSAML\Test\Module\ratelimit\Utils\SampleLimiter;

#[CoversClass(UserPassBaseLimiter::class)]
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
