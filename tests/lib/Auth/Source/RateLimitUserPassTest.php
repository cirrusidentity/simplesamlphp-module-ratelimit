<?php

use CirrusIdentity\SSP\Test\InMemoryStore;
use PHPUnit\Framework\TestCase;
use SimpleSAML\Auth\Source;
use SimpleSAML\Module\core\Auth\UserPassBase;
use SimpleSAML\Module\ratelimit\Auth\Source\RateLimitUserPass;
use SimpleSAML\Store;

use AspectMock\Test as test;

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

    public function testWrapAdminSource()
    {
        //given: an authsource that delegates to AdminPassword
        $authsourceConfig = [
                'class' => 'ratelimit:RateLimitUserPass',
                'delegate' => [
                    'core:AdminPassword',
                ],
        ];
        $info = [
          'AuthId' => 'admin'
        ];
        $source = new RateLimitUserPass($info, $authsourceConfig);
        $store = Store::getInstance();

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

    private function checkPassword(RateLimitUserPass $source, string $username, string $password):bool
    {
        try {
            $source->login($username, $password);
            return true;
        } catch (\SimpleSAML\Error\Error $e) {
            $this->assertEquals('WRONGUSERPASS', $e->getErrorCode());
            return false;
        }
    }

}