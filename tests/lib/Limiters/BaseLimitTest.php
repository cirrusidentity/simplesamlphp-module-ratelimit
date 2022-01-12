<?php

namespace SimpleSAML\Test\Module\ratelimit\Limiters;

use CirrusIdentity\SSP\Test\InMemoryStore;
use PHPUnit\Framework\TestCase;
use SimpleSAML\Configuration;
use SimpleSAML\Module\ratelimit\Limiters\UserPassBaseLimiter;
use SimpleSAML\Store;
use SimpleSAML\Store\StoreFactory;

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
        for ($i = 1; $i <= 3; $i++) {
            // First 3 attempts should not be blocked
            $this->assertEquals('continue', $limiter->allow($username, $password), "Attempt $i");
            $this->assertEquals($i, $limiter->postFailure($username, $password));
            $this->assertEquals($i, $this->getStoreValueFor($limiter->getRateLimitKey($username, $password)));
        }
        // After 3 failed attempts it should be blocked
        $this->assertEquals('block', $limiter->allow($username, $password));

        // Sleep until the next window, and counter should be reset
        usleep(4020000);
        $this->assertNull(
            $this->getStoreValueFor($limiter->getRateLimitKey($username, $password)),
            'Value not expected in store'
        );
        $this->assertEquals('continue', $limiter->allow($username, $password));
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
    private function getStoreValueFor(string $key)
    {
        /** @var string $storeType */
        $storeType = Configuration::getConfig()->getString('store.type', 'phpsession');
        $store = StoreFactory::getInstance($storeType);
        $this->assertNotFalse($store, 'Store was not configured for ' . $storeType);
        /** @var int|null */
        return $store->get('int', 'ratelimit-' . $key);
    }
}
