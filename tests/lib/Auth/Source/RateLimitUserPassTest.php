<?php

namespace SimpleSAML\Module\ratelimit\Auth\Source;

use AspectMock\Test as test;
use CirrusIdentity\SSP\Test\InMemoryStore;
use PHPUnit\Framework\TestCase;
use SimpleSAML\Configuration;
use SimpleSAML\Error;
use SimpleSAML\Store\StoreFactory;
use SimpleSAML\Test\Module\ratelimit\Limiters\ExceptionThrowingLimiter;

class RateLimitUserPassTest extends TestCase
{
    private $mockHttp;

    protected function setUp(): void
    {
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

    public function testWrapAdminSource(): void
    {
        //given: an authsource that delegates to AdminPassword
        $authsourceConfig = [
            'ratelimit:RateLimitUserPass',
            'delegate' => [
                'core:AdminPassword',
            ],
        ];
        $info = [
          'AuthId' => 'admin'
        ];
        $source = new RateLimitUserPass($info, $authsourceConfig);
        $storeType = Configuration::getConfig()->getString('store.type', 'phpsession');
        $store = StoreFactory::getInstance($storeType);
        $this->assertNotFalse($store, 'Store was not configured for ' . $storeType);

        //when: attempting authentication with the correct password
        $this->assertTrue(
            $this->checkPassword($source, 'admin', 'secret')
        );

        //when: attempting the login limit
        for ($attempt = 1; $attempt <= 20; $attempt++) {
            // Limiter allows attempt
            $this->assertTrue($source->allowLoginAttempt('admin', 'wrongpassword'), "attempt $attempt");
            $this->assertFalse(
                $this->checkPassword($source, 'admin', 'wrongpassword')
            );
            // Confirm attempt track
            $this->assertEquals($attempt, $store->get('int', 'ratelimit-user-admin'), "attempt $attempt");
        }
        // then: 20th attempt will trigger the limiter
        $this->assertFalse($source->allowLoginAttempt('admin', 'wrongpassword'), "attempt $attempt");
        $this->assertFalse(
            $this->checkPassword($source, 'admin', 'wrongpassword')
        );
        $this->assertFalse(
            $this->checkPassword($source, 'admin', 'secret'),
            'Even correct password is locked out'
        );
    }

    /**
     * Test that an exception in our limiter doesn't prevent the correct operation of the
     * auth source
     */
    public function testLimiterExceptionDoesntBlockLogins(): void
    {
        $authsourceConfig = [
            'ratelimit:RateLimitUserPass',
            'delegate' => [
                'core:AdminPassword',

            ],
            'ratelimit' => [
                0 => [
                    ExceptionThrowingLimiter::class,
                ],
                1 => [
                    'user',
                    'window' => 'PT5M',
                    'limit' => 20
                ]
            ]
        ];
        $info = [
            'AuthId' => 'admin'
        ];
        $source = new RateLimitUserPass($info, $authsourceConfig);
        $storeType = Configuration::getConfig()->getString('store.type', 'phpsession');
        $store = StoreFactory::getInstance($storeType);
        $this->assertNotFalse($store, 'Store was not configured for ' . $storeType);

        $this->assertTrue(
            $this->checkPassword($source, 'admin', 'secret')
        );

        $this->assertFalse(
            $this->checkPassword($source, 'admin', 'wrong')
        );
        $this->assertEquals(1, $store->get('int', 'ratelimit-user-admin'), "attempt 1");
    }

    private function checkPassword(RateLimitUserPass $source, string $username, string $password): bool
    {
        try {
            $source->login($username, $password);
            return true;
        } catch (Error\Error $e) {
            $this->assertEquals('WRONGUSERPASS', $e->getErrorCode());
            return false;
        }
    }
}
