<?php

namespace SimpleSAML\Test\Module\ratelimit\Limiters;

use SimpleSAML\Configuration;
use SimpleSAML\Module\ratelimit\Limiters\PasswordStuffingLimiter;
use SimpleSAML\Module\ratelimit\Limiters\UsernameLimiter;
use SimpleSAML\Module\ratelimit\Limiters\UserPassBaseLimiter;

class PasswordStuffingLimiterTest extends BaseLimitTest
{

    protected function getLimiter(array $config): UserPassBaseLimiter
    {
        return new PasswordStuffingLimiter(Configuration::loadFromArray($config));
    }

    /**
     * Test to confirm the password hash is time dependent
     */
    public function testKeyVariesWithWindow(): void
    {
        $config = [
          'window' => 'PT2S'
        ];

        $limiter = $this->getLimiter($config);

        $password = 'abcXYZ123';
        //TODO: adjust time to be the start of a window
        $result = $limiter->getRateLimitKey('efg', $password);
        $this->assertEquals($result, $limiter->getRateLimitKey('xyz', $password));
        $this->assertEquals($result, $limiter->getRateLimitKey('abc', $password));

        sleep(3);
        // Next window should have different keys
        $newKey = $limiter->getRateLimitKey('efg', $password);
        $this->assertNotEquals($result, $newKey, 'Key should vary with window');
        $this->assertEquals($newKey, $limiter->getRateLimitKey('xyz', $password));
    }

    /**
     * Confirm that the key changes with different passwords
     */
    public function testKeyVariesWithInput(): void
    {
        $limiter = $this->getLimiter([]);
        $key1 = $limiter->getRateLimitKey('u', 'p1');
        $key2 = $limiter->getRateLimitKey('u', 'p2');
        $this->assertNotEquals($key1, $key2, 'keys should vary');
    }
}
