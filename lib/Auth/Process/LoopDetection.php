<?php

declare(strict_types=1);

namespace SimpleSAML\Module\ratelimit\Auth\Process;

use Exception;
use SimpleSAML\Auth\ProcessingFilter;
use SimpleSAML\Auth\State;
use SimpleSAML\Configuration;
use SimpleSAML\Logger;
use SimpleSAML\Module;
use SimpleSAML\Session;
use SimpleSAML\Utils;

/**
 * Give a warning to the user if we receive multiple requests in a short time.
 *
 * @package SimpleSAMLphp
 */
class LoopDetection extends ProcessingFilter
{

    /**
     * Number of seconds since Previous SSO.
     * @var integer
     */
    private $secondsSinceLastSso;


    /**
     * The number of loops before warning.
     * @var integer
     */
    private $loopsBeforeWarning;

    /**
     * Only log a warning.
     * @var boolean
     */
    private $logOnly;


    public function __construct(&$config, $reserved)
    {
        parent::__construct($config, $reserved);
        $config = Configuration::loadFromArray($config);

        $this->secondsSinceLastSso = $config->getInteger('secondsSinceLastSso');
        $this->loopsBeforeWarning = $config->getInteger('loopsBeforeWarning');
        $this->logOnly = $config->getBoolean('logOnly', true);
    }


    /**
     * Process an authentication response.
     *
     * This function checks how long it is since the last time the user was authenticated.
     * If it is too short a while since and repeats, we will show a warning to the user.
     *
     * @param array $state The state of the response.
     * @throws Exception
     */
    public function process(&$state): void
    {

        $session = Session::getSessionFromRequest();

        if (!array_key_exists('PreviousSSOTimestamp', $state)) {
            /*
             * No timestamp from the previous SSO to this SP. This is the first
             * time during this session.
             */
            $session->setData('ratelimit:loopDetection', 'Count', 0);
            return;
        }

        $timeDelta = time() - $state['PreviousSSOTimestamp'];
        if ($timeDelta >= $this->secondsSinceLastSso) {
            // At least 10 seconds since last attempt
            $session->setData('ratelimit:loopDetection', 'Count', 0);
            return;
        }

        $loopDetectionCount = $session->getData('ratelimit:loopDetection', 'Count') + 1;
        Logger::debug('LoopDetectionCount: ' . $loopDetectionCount);

        $session->setData('ratelimit:loopDetection', 'Count', $loopDetectionCount);

        $entityId =  $state['Destination']['entityid'] ?? 'UNKNOWN';

        if ($loopDetectionCount <= $this->loopsBeforeWarning) {
            if ($loopDetectionCount > 1) {
                Logger::warning('LoopDetectionCount: ' . $loopDetectionCount .
                                ' entityId: ' . var_export($entityId, true));
            }
            return;
        }

        Logger::warning('LoopDetection: Only ' . $timeDelta .
            ' seconds since last SSO for this user from the SP ' . var_export($entityId, true) .
            ' LoopDetectionCount ' . $loopDetectionCount);

        if (!$this->logOnly) {
            // Set the loop counter back to 0
            $session->setData('ratelimit:loopDetection', 'Count', 0);
            // Save state and redirect
            $id = State::saveState($state, 'ratelimit:loop_detection');
            $url = Module::getModuleURL('ratelimit/loop_detection.php');
            $httpUtils = new Utils\HTTP();
            $httpUtils->redirectTrustedURL($url, ['StateId' => $id]);
        }
    }
}
