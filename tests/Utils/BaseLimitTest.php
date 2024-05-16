<?php

declare(strict_types=1);

namespace SimpleSAML\Test\Module\ratelimit\Utils;

use PHPUnit\Framework\TestCase;
use SimpleSAML\Assert\Assert;
use SimpleSAML\Configuration;
use SimpleSAML\Module\ratelimit\Limiters\UserPassBaseLimiter;
use SimpleSAML\Module\ratelimit\PreAuthStatusEnum;
use SimpleSAML\Store\StoreFactory;
use SimpleSAML\TestUtils\InMemoryStore;

use function sleep;

abstract class BaseLimitTest extends TestCase
{
    protected function setUp(): void
    {
        Configuration::clearInternalState();
    }

    protected function tearDown(): void
    {
        StoreFactory::clearInternalState();
        InMemoryStore::clearInternalState();
        Configuration::clearInternalState();
    }

    abstract protected function getLimiter(array $config): UserPassBaseLimiter;

    /**
     * If the current rate limit window has less than $miTime seconds left, sleep till the
     * next window.
     * @param UserPassBaseLimiter $limiter
     * @param int $minTime minimum seconds left in the window
     * @return void
     */
    public function waitTillWindowHasAtLeastMinTime(UserPassBaseLimiter $limiter, int $minTime): void
    {
        $time = time();
        $startingWindow = $limiter->determineWindowExpiration($time);
        $windowTimeLeft = $startingWindow - $time;
        $this->assertGreaterThanOrEqual(0, $windowTimeLeft);
        if ($windowTimeLeft < $minTime) {
            echo "Waiting for new wait limit window " . $windowTimeLeft;
            /** @psalm-suppress InvalidArgument */
            sleep($windowTimeLeft);
            $this->assertNotEquals(
                $startingWindow,
                $limiter->determineWindowExpiration(time()),
                "Unable to start new window"
            );
        } else {
            echo "$windowTimeLeft seconds left in window $startingWindow";
        }
    }

    /**
     * Some tests require sleeping until the next time window starts.
     * @param int $currentWindow The current time window
     * @param UserPassBaseLimiter $limiter
     * @return void
     */
    public function sleepTillNextWindow(int $currentWindow, UserPassBaseLimiter $limiter): void
    {
        while ($currentWindow == $limiter->determineWindowExpiration(time())) {
            sleep(1);
        }
        // Pending discussion from https://github.com/simplesamlphp/simplesamlphp-test-framework/issues/5
        // Sleep an extra second since inMemoryStore considers data expired 1 second after expiration date
        sleep(1);
    }

    /**
     *  Test window calculation
     */
    public function testWindowExpiration(): void
    {
        $config = [];
        $limiter = $this->getLimiter($config);
        // Default window is 300 seconds
        $this->assertEquals('300', $limiter->determineWindowExpiration(5));
        $this->assertEquals('300', $limiter->determineWindowExpiration(299));
        $this->assertEquals('600', $limiter->determineWindowExpiration(300));
        $this->assertEquals('600', $limiter->determineWindowExpiration(301));
        $this->assertEquals('600', $limiter->determineWindowExpiration(599));
        $this->assertEquals('900', $limiter->determineWindowExpiration(600));

        $config['window'] = 'PT38S';
        $limiter = $this->getLimiter($config);
        // Window is 38 seconds
        $this->assertEquals('38038', $limiter->determineWindowExpiration(38000));
        $this->assertEquals('38038', $limiter->determineWindowExpiration(38037));
        $this->assertEquals('38076', $limiter->determineWindowExpiration(38038));
    }

    /**
     * Test allow interactions
     */
    public function testAllowAndFailure(): void
    {
        $config = [
            'limit' => 3,
            'window' => 'PT3S'
        ];
        $limiter = $this->getLimiter($config);
        $username = 'Homer';
        $password = 'Beer';
        $this->waitTillWindowHasAtLeastMinTime($limiter, 2);
        $startingWindow = $limiter->determineWindowExpiration(time());
        for ($i = 1; $i <= 3; $i++) {
            // First 3 attempts should not be blocked
            $this->assertEquals(PreAuthStatusEnum::CONTINUE, $limiter->allow($username, $password), "Attempt $i");
            $currentWindow = $limiter->determineWindowExpiration(time());
            $this->assertEquals($startingWindow, $currentWindow, 'Cache window changed during test');
            $this->assertEquals($i, $limiter->postFailure($username, $password));
            $this->assertEquals($i, $this->getStoreValueFor($limiter->getRateLimitKey($username, $password)));
        }
        // After 3 failed attempts it should be blocked
        $this->assertEquals(PreAuthStatusEnum::BLOCK, $limiter->allow($username, $password));

        // Sleep until the next window, and counter should be reset
        $this->sleepTillNextWindow($startingWindow, $limiter);
        $this->assertNotEquals(
            $startingWindow,
            $limiter->determineWindowExpiration(time()),
            'Next cache window expected'
        );
        $this->assertNull(
            $this->getStoreValueFor($limiter->getRateLimitKey($username, $password)),
            'Value not expected in store'
        );
        $this->assertEquals(PreAuthStatusEnum::CONTINUE, $limiter->allow($username, $password));
    }

    /**
     * By default this is a noop
     */
    public function testDefaultSuccess(): void
    {
        $limiter = $this->getLimiter([]);
        $username = 'Homer';
        $password = 'Beer';
        $limiter->postSuccess($username, $password);
        $this->assertNull($this->getStoreValueFor($limiter->getRateLimitKey($username, $password)));
    }

    /**
     * Confirm that the key changes with different inputs. Example: key for username changes with
     * different usernames
     */
    abstract public function testKeyVariesWithInput(): void;

    /**
     * Get the stored count value or null if not stored.
     * @param string $key
     * @return int|null
     * @throws \SimpleSAML\Error\CriticalConfigurationError
     */
    private function getStoreValueFor(string $key): ?int
    {
        $storeType = Configuration::getConfig()->getOptionalString('store.type', 'phpsession');
        $store = StoreFactory::getInstance($storeType);
        $this->assertNotFalse($store, 'Store was not configured for ' . $storeType);
        /** @var int|null */
        return $store->get('int', 'ratelimit-' . $key);
    }
}
