<?php

declare(strict_types=1);

namespace SimpleSAML\Test\Module\ratelimit\Limiters;

use PHPUnit\Framework\Attributes\{CoversClass, DataProvider};
use RuntimeException;
use SimpleSAML\Configuration;
use SimpleSAML\Module\ratelimit\Limiters\{PasswordStuffingLimiter, UserPassBaseLimiter};
use SimpleSAML\TestUtils\InMemoryStore;
use SimpleSAML\Test\Module\ratelimit\Utils\BaseLimitTest;

use function sleep;

#[CoversClass(PasswordStuffingLimiter::class)]
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

    public function testKeyIsConstantInTimeWindow(): void
    {
        $limiter = $this->getLimiter([]);
        $key1 = $limiter->getRateLimitKey('u1', 'p1');
        $key2 = $limiter->getRateLimitKey('u2', 'p1');
        $this->assertEquals($key1, $key2, 'keys should remain the same');
    }

    private function setConfigBasedOnSalt(string $salt): void
    {
        Configuration::setPreLoadedConfig(
            Configuration::loadFromArray([
                'secretsalt' => $salt,
                'module.enable' => [
                    'ratelimit' => true,
                ],
                'store.type' => InMemoryStore::class,
            ])
        );
    }

    /**
     */
    #[DataProvider('saltProvider')]
    public function testVariousSalts(string $salt): void
    {
        $this->setConfigBasedOnSalt($salt);
        $limiter = $this->getLimiter([]);
        $key1 = $limiter->getRateLimitKey('u', 'p1');
        $this->assertGreaterThan(strlen('password-'), strlen($key1));
    }

    public static function saltProvider(): array
    {
        return [
            // 12 seems like minimum salt lenght
            ['123456789012'],
            // longer than 22
          ['123456789012345678901234568']
        ];
    }

    /**
     */
    #[DataProvider('badSaltProvider')]
    public function testBadSaltsDoesntResultInBlankKey(string $salt): void
    {
        $this->setConfigBasedOnSalt($salt);
        $limiter = $this->getLimiter([
            'window' => 'PT2S'
        ]);
        $password = 'p!% *';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unable to generate password hash key');
        $limiter->getRateLimitKey('u1', $password);
    }

    public static function badSaltProvider(): array
    {
        return [
            // too short
            ['a'],
            // special characters
            ['! /*$√'],
        ];
    }
}
