<?php

namespace SimpleSAML\Test\Module\ratelimit\Limiters;

use AspectMock\Test as test;
use CirrusIdentity\SSP\Test\InMemoryStore;
use CirrusIdentity\SSP\Test\MockHttp;
use PHPUnit\Framework\TestCase;
use SimpleSAML\Configuration;
use SimpleSAML\Module\ratelimit\Limiters\DeviceCookieLimiter;
use SimpleSAML\Store\StoreFactory;

class DeviceCookieLimiterTest extends TestCase
{

    private $mockHttp;

    protected function setUp(): void
    {
        test::clean();
        // Stub the setCookie method
        $this->mockHttp = test::double('SimpleSAML\Utils\HTTP', [
            'setCookie' => true,
        ]);
    }

    protected function tearDown(): void
    {
        InMemoryStore::clearInternalState();
        unset($_COOKIE['deviceCookie']);
    }


    public function testSuccessWithNoPreexistingCookie(): void
    {
        $limiter = $this->getLimiter([]);
        $limiter->postSuccess('user', 'password');
        $deviceCookie = $this->getDeviceCookieFromMock();
        $_COOKIE['deviceCookie'] = $deviceCookie;
        $key = 'ratelimit-' . $limiter->getRateLimitKey('user', 'pass');
        $store = $limiter->getStore();
//        $store->dump();
        $val = $store->get('array', $key);
        $this->assertNotNull($val, $key . ' expected');
        $this->assertEquals('user', $val['user']);
        $this->assertEquals(0, $val['count']);
    }

    public function testSuccessWithPreexistingCookie(): void
    {
        //given: an old device cookie
        $_COOKIE['deviceCookie'] = 'oldCookie';
        $limiter = $this->getLimiter([]);
        $key = 'ratelimit-' . $limiter->getRateLimitKey('user', 'pass');
        $oldVal = ['notimportant'];
        $store = $limiter->getStore();
        $store->set('array', $key, $oldVal);

        //when: authenticating
        $limiter->postSuccess('user', 'password');

        //then: old cookie is removed from store
        $this->assertNull($store->get('array', $key));

        // and new cookie should be created
        $deviceCookie = $this->getDeviceCookieFromMock();
        $this->assertNotEquals('oldCookie', $deviceCookie);
        $_COOKIE['deviceCookie'] = $deviceCookie;
        $key = 'ratelimit-' . $limiter->getRateLimitKey('user', 'pass');
        $val = $store->get('array', $key);
        $this->assertNotNull($val);
        $this->assertEquals('user', $val['user']);
        $this->assertEquals(0, $val['count']);
    }

    public function testDeviceCookieAllowsAuth(): void
    {
        $limiter = $this->getLimiter([]);

        //expect: auth with out device cookie is continue
        $this->assertEquals('continue', $limiter->allow('me', 'a'));

        //given: an existing cookie in the stroe
        $limiter->postSuccess('me', 'pass');
        $deviceCookie = $this->getDeviceCookieFromMock();
        $_COOKIE['deviceCookie'] = $deviceCookie;


        $this->assertEquals(
            'allow',
            $limiter->allow('me', 'a'),
            'User matches device cookie, so is allowed'
        );

        $this->assertEquals(
            'continue',
            $limiter->allow('typoe', 'a'),
            'User does not match device cookie, so go to other rules'
        );
        $_COOKIE['deviceCookie'] = 'not-in-store';
        $this->assertEquals(
            'continue',
            $limiter->allow('me', 'a'),
            'Device cookie not in store means go to other rules'
        );
    }

    public function testFailedAttemptsRemoveCookie(): void
    {
        $limiter = $this->getLimiter([
            'limit' => 2,
        ]);
        $store = $limiter->getStore();

        //Expect: no cookie is not tracked
        $this->assertEquals(0, $limiter->postFailure('u', 'p'));

        //Expect: non-existant cookie is not tracked
        $_COOKIE['deviceCookie'] = 'not-in-store';
        $this->assertEquals(0, $limiter->postFailure('u', 'p'));
        $key = $limiter->getRateLimitKey('u', 'p');
        $this->assertNull($store->get('array', 'ratelimit-' . $key));

        // Set a cookie
        $limiter->postSuccess('u', 'p');
        $_COOKIE['deviceCookie'] = $this->getDeviceCookieFromMock();

        //Expect: cookie and store ignored for non-matching user
        $this->assertEquals(0, $limiter->postFailure('u2', 'p'));

        // Expect: failure for use increments count
        $this->assertEquals(1, $limiter->postFailure('u', 'p'));
        //sanity check that allow method still returns allow
        $this->assertEquals('allow', $limiter->allow('u', 'p'));

        $key = $limiter->getRateLimitKey('u', 'p');
        $result = $store->get('array', 'ratelimit-' . $key);
        $this->assertEquals(1, $result['count']);
        $this->assertEquals('u', $result['user']);
        // Expect: hitting limit to unset cookie
        $this->assertEquals(2, $limiter->postFailure('u', 'p'));
        $this->assertNull($store->get('array', 'ratelimit-' . $key));
        //sanity check that allow method not returns continue
        $this->assertEquals('continue', $limiter->allow('u', 'p'));
    }

    private function getDeviceCookieFromMock(string $cookieName = 'deviceCookie')
    {
        $invocations = $this->mockHttp->getCallsForMethod('setCookie');
        $this->assertCount(1, $invocations, 'Unexpected # of setCookie invocations');
        $args = $this->mockHttp->getCallsForMethod('setCookie')[0];
        $this->assertEquals($cookieName, $args[0]);
        return $args[1];
    }

    /**
     * Confirm that the key changes with different device cookies
     */
    public function testKeyVariesWithInput(): void
    {
        $_COOKIE['deviceCookie'] = 'abc';
        $limiter = $this->getLimiter([]);
        $this->assertEquals('device-abc', $limiter->getRateLimitKey('u', 'p'));
        $_COOKIE['deviceCookie'] = 'xyz';
        $this->assertEquals('device-xyz', $limiter->getRateLimitKey('u', 'p'));
        $limiter = $this->getLimiter(['deviceCookieName' => 'deviceId']);
        $_COOKIE['deviceId'] = 'mno';
        $this->assertEquals('device-mno', $limiter->getRateLimitKey('u', 'p'));
    }

    protected function getLimiter(array $config): DeviceCookieLimiter
    {
        return new DeviceCookieLimiter(Configuration::loadFromArray($config));
    }
}
