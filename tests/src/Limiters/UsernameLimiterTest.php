<?php

declare(strict_types=1);

namespace SimpleSAML\Test\Module\ratelimit\Limiters;

use PHPUnit\Framework\Attributes\CoversClass;
use SimpleSAML\Configuration;
use SimpleSAML\Module\ratelimit\Limiters\{UsernameLimiter, UserPassBaseLimiter};
use SimpleSAML\Test\Module\ratelimit\Utils\BaseLimitTest;

#[CoversClass(UserNameLimiter::class)]
class UsernameLimiterTest extends BaseLimitTest
{
    /**
     * Confirm that the key changes with different passwords
     */
    public function testKeyVariesWithInput(): void
    {
        $limiter = $this->getLimiter([]);
        $key1 = $limiter->getRateLimitKey('u1', 'p');
        $key2 = $limiter->getRateLimitKey('u2', 'p');
        $this->assertNotEquals($key1, $key2, 'keys should vary');
    }

    protected function getLimiter(array $config): UserPassBaseLimiter
    {
        return new UsernameLimiter(Configuration::loadFromArray($config));
    }
}
