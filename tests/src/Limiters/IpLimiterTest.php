<?php

declare(strict_types=1);

namespace SimpleSAML\Test\Module\ratelimit\Limiters;

use PHPUnit\Framework\Attributes\{CoversClass, DataProvider};
use SimpleSAML\Configuration;
use SimpleSAML\Module\ratelimit\Limiters\{IpLimiter, UserPassBaseLimiter};
use SimpleSAML\Module\ratelimit\PreAuthStatusEnum;
use SimpleSAML\Test\Module\ratelimit\Utils\BaseLimitTest;

#[CoversClass(IpLimiter::class)]
class IpLimiterTest extends BaseLimitTest
{
    protected function setUp(): void
    {
        $_SERVER['REMOTE_ADDR'] = '12.3.7.12';
    }

    protected function getLimiter(array $config): UserPassBaseLimiter
    {
        return new IpLimiter(Configuration::loadFromArray($config));
    }

    /**
     * @param string $ip The user's ip address
     * @param bool $ignoreExpected If this IP should be ignored
     */
    #[DataProvider('ipWhitelistProvider')]
    public function testIpWhitelist(string $ip, bool $ignoreExpected): void
    {
        $config = [
            'limit' => 1,
            'whitelist' => [
                '1.2.3.4',
                '10.2.0.0/24'
            ]
        ];
        $_SERVER['REMOTE_ADDR'] = $ip;


        $limiter = $this->getLimiter($config);
        $this->assertEquals($ignoreExpected ? 0 : 1, $limiter->postFailure('u', 'p'));
        $this->assertEquals(
            $ignoreExpected ? PreAuthStatusEnum::CONTINUE : PreAuthStatusEnum::BLOCK,
            $limiter->allow('u', 'p')
        );
    }

    public static function ipWhitelistProvider(): array
    {
        return [
            ['12.3.7.12', false],
            ['1.2.3.4', true],
            ['10.1.255.255', false],
            ['10.2.0.0', true],
            ['10.2.0.255', true],
            ['10.2.1.0', false],
        ];
    }

    /**
     * Confirm that the key changes with different IPs
     */
    public function testKeyVariesWithInput(): void
    {
        /** @var IpLimiter $limiter */
        $limiter = $this->getLimiter([]);
        $key1 = $limiter->getRateLimitKey('u', 'p');
        $limiter->setClientIpAddress('1.2.3.4');
        $key2 = $limiter->getRateLimitKey('u', 'p');
        $this->assertNotEquals($key1, $key2, 'keys should vary');
    }
}
