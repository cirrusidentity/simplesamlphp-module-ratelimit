<?php

namespace SimpleSAML\Test\Module\ratelimit\Auth\Process;

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
use SimpleSAML\Utils\HTTP;

class LoopDetectionTest extends TestCase
{
    public function setUp(): void
    {
        (new StateClearer())->clearSSPState();
    }


    protected function tearDown(): void
    {
        InMemoryStore::clearInternalState();
    }


    private function getState(): array
    {
        $state = [
           'PreviousSSOTimestamp' => strtotime('2011-11-11T11:11:11Z'),
        ];
        return $state;
    }


    /**
     * @return (false|int|string)[]
     *
     * @psalm-return array{class: 'ratelimit:LoopDetection', secondsSinceLastSso: 1,
     * loopsBeforeWarning: 5, logOnly: false}
     */
    private function getConfig(): array
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

    public function testLoopDetectionRedirect(): void
    {
        $state = $this->getState();
        $config = $this->getConfig();
        $session = $this->getSession();
        $expectedUrl = 'http://localhost/module.php/ratelimit/loop_detection';

        $emptyState = [];
        $mockHttp = $this->createMock(HTTP::class);
        $mockHttp->method('redirectTrustedURL')
            ->with(
                $expectedUrl,
                $this->arrayHasKey('StateId')
            )
            ->willThrowException(new \Exception('Redirect expected'));

        $source = new LoopDetection($config, null);
        $source->setHttp($mockHttp);

        $source->process($emptyState);

        $session->setData('ratelimit:loopDetection', 'Count', 11);
        $state['PreviousSSOTimestamp'] = time();

        $this->expectExceptionMessage('Redirect expected');
        $source->process($state);
    }

    public function testLoopDetectionLogOnly(): void
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

    public function testLoopDetectionIncrementCount(): void
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
