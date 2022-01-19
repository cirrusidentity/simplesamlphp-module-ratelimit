<?php

namespace SimpleSAML\Test\Module\ratelimit\Auth\Process;

use AspectMock\Test as test;
use CirrusIdentity\SSP\Test\Capture\RedirectException;
use CirrusIdentity\SSP\Test\InMemoryStore;
use CirrusIdentity\SSP\Test\MockHttp;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use SimpleSAML\Auth\Source;
use SimpleSAML\Module\core\Auth\UserPassBase;
use SimpleSAML\Module\ratelimit\Auth\Process\LoopDetection;
use SimpleSAML\Session;
use SimpleSAML\TestUtils\ClearStateTestCase;
use SimpleSAML\TestUtils\StateClearer;

class LoopDetectionTest extends TestCase
{
    public function setUp(): void
    {
        (new StateClearer())->clearSSPState();
        MockHttp::throwOnRedirectTrustedURL();
    }


    protected function tearDown(): void
    {
        InMemoryStore::clearInternalState();
        test::clean(); // remove all registered test doubles
    }


    private function getState()
    {
        $state = [
           'PreviousSSOTimestamp' => strtotime('2011-11-11T11:11:11Z'),
        ];
        return $state;
    }


    private function getConfig()
    {
        $config = [
            'class' => 'ratelimit:LoopDetection',
            'secondsSinceLastSso' => 1,
            'loopsBeforeWarning' => 5,
            'logOnly' => false,
        ];
        return $config;
    }

    private function getSession(): Session
    {
        $session = Session::getSessionFromRequest();
        //cli/phpunit sessions don't have session ids, but SessionHandlerStore needs a session id to save dirty state
        $class = new ReflectionClass(Session::class);
        $prop = $class->getProperty('sessionId');
        $prop->setAccessible(true);
        $prop->setValue($session, 'mockedSessionId');
        $this->assertEquals('mockedSessionId', $session->getSessionId());
        return $session;
    }

    public function testLoopDetectionRedirect()
    {
        $state = $this->getState();
        $config = $this->getConfig();
        $session = $this->getSession();
        $expectedUrl = 'http://localhost/module.php/ratelimit/loop_detection.php';

        $emptyState = [];
        $source = new LoopDetection($config, null);

        $source->process($emptyState);

        $session->setData('ratelimit:loopDetection', 'Count', 11);
        $state['PreviousSSOTimestamp'] = time();

        try {
            $source->process($state);
            $this->fail('Redirect exception expected');
        } catch (RedirectException $e) {
            $this->assertEquals('redirectTrustedURL', $e->getMessage());
            $this->assertEquals(
                $expectedUrl,
                $e->getUrl(),
                "First argument should be the redirect url"
            );
            $this->assertArrayHasKey('StateId', $e->getParams(), "StateId is added");
        }
    }

    public function testLoopDetectionLogOnly()
    {
        $state = $this->getState();
        $config = $this->getConfig();
        $config['logOnly'] = true;
        $session = $this->getSession();

        $emptyState = [];
        $source = new LoopDetection($config, null);

        $source->process($emptyState);

        $session->setData('ratelimit:loopDetection', 'Count', 11);
        $state['PreviousSSOTimestamp'] = time();

        $source->process($state);
        // In Log Only mode the count should increment but user flow show not be impacted.
        $this->assertEquals(12, $session->getData('ratelimit:loopDetection', 'Count'));
    }

    public function testLoopDetectionIncrementCount()
    {
        $state = $this->getState();
        $state['PreviousSSOTimestamp'] = time();
        $config = $this->getConfig();
        $session = $this->getSession();
        $session->setData('ratelimit:loopDetection', 'Count', 1);

        $source = new LoopDetection($config, null);
        $source->process($state);
        $this->assertEquals(2, $session->getData('ratelimit:loopDetection', 'Count'));
    }
}
