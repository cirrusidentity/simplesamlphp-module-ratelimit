<?php

use AspectMock\Test as test;
use CirrusIdentity\SSP\Test\Capture\RedirectException;
use CirrusIdentity\SSP\Test\InMemoryStore;
use CirrusIdentity\SSP\Test\MockHttp;
use SimpleSAML\Module\ratelimit\Auth\Process\LoopDetection;


class LoopDetectionTest extends \PHPUnit\Framework\TestCase
{

    private $mockHttp;


    public function setUp(): void
    {
        putenv('SIMPLESAMLPHP_CONFIG_DIR=' . dirname(dirname(dirname(__DIR__))) . '/config');
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
            'secondssincelastsso' => 1,
            'loopsbeforewarning' => 5,
            'logonly' => FALSE,
        ];
        return $config;
    }


    public function testLoopDetectionSessionVar()
    {
        $_SESSION['loopDetectionCount'] = 10;
        $this->assertEquals(10, $_SESSION['loopDetectionCount']);

    }


    public function testPreviousSSOGreaterThan()
    {
        $state = $this->getState();
        $newTime = $state['PreviousSSOTimestamp'] + 10;
        $this->assertGreaterThan($state['PreviousSSOTimestamp'],$newTime);
    }


    public function testPreviousSSOLessThan()
    {
        $state = $this->getState();
        $newTime = $state['PreviousSSOTimestamp'] - 10;
        $this->assertLessThan($state['PreviousSSOTimestamp'],$newTime);
    }


    public function testLoopDetectionRedirect()
    {
        $state = $this->getState();
        $config = $this->getConfig();
        $expectedUrl = 'http://localhost/module.php/ratelimit/loop_detection.php';

        $x = [];
        $source = new LoopDetection($config, NULL);

        $source->process($x);

        $_SESSION['loopDetectionCount'] = 11;
        $_SERVER['REQUEST_URI'] = 'http://localhost';
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $state['PreviousSSOTimestamp'] = time();
        $stateId = null;


        try {
            $resp = $source->process($state);
            $this->fail('Redirect exception expected');
        } catch (RedirectException $e) {
            $this->assertEquals('redirectTrustedURL', $e->getMessage());
            $this->assertEquals(
                $expectedUrl,
                $e->getUrl(),
                "First argument should be the redirect url"
            );
            $this->assertArrayHasKey('StateId', $e->getParams(), "StateId is added");
            $stateId = $e->getParams()['StateId'];

        }

    }

    public function testLoopDetectionLogOnly()
    {
        $state = $this->getState();
        $config = $this->getConfig();
        $config['logonly'] = TRUE;

        $expectedUrl = 'http://localhost/module.php/ratelimit/loop_detection.php';

        $x = [];
        $source = new LoopDetection($config, NULL);

        $source->process($x);

        $_SESSION['loopDetectionCount'] = 11;
        $_SERVER['REQUEST_URI'] = 'http://localhost';
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $state['PreviousSSOTimestamp'] = time();
        $stateId = null;


        try {
            $source->process($state);
            $this->fail('Redirect exception is not expected');
        } catch (Exception $e) {
            $this->assertNotInstanceOf("CirrusIdentity\\SSP\\Test\\Capture\\RedirectException", $e);

        }

    }
}
