<?php

declare(strict_types=1);

namespace SimpleSAML\Module\ratelimit\Auth\Process;

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
     * Process a authentication response.
     *
     * This function checks how long it is since the last time the user was authenticated.
     * If it is to short a while since and repeats, we will show a warning to the user.
     *
     * @param array $state  The state of the response.
     */
    public function process(&$state): void
    {

        assert(is_array($state));

        if (!array_key_exists('PreviousSSOTimestamp', $state)) {
            /*
             * No timestamp from the previous SSO to this SP. This is the first
             * time during this session.
             */
            //FIXME: SSP can be configured to user other sessions, rather than php's built in
            $_SESSION['loopDetectionCount'] = 0;
            return;
        }

        $timeDelta = time() - $state['PreviousSSOTimestamp'];
        if ($timeDelta >= $this->secondsSinceLastSso) {
            // At least 10 seconds since last attempt
            //FIXME: should this reset the loopDetectionCount... Otherwise if they come back an hour later
            // they would get prompted
            return;
        }

        $loopDetectionCount = $_SESSION['loopDetectionCount'] + 1;

        Logger::debug('LoopDetectionCount: ' . $loopDetectionCount);

        //FIXME: SSP can be configured to user other sessions, rather than php's built in
       $session = Session::getSessionFromRequest();
        // key should also be prefixed with 'ratelimt:'
        //$_SESSION['loopDetectionCount'] = $loopDetectionCount;
       //See PowerIdPDisco for some sample usage
        $session->setData('ratelimit:loopDetection', 'loopDetectionCount', someData);


        if ($loopDetectionCount <= $this->loopsBeforeWarning) {
            return;
        }


        //FIXME: use null coalescing operation
        $entityId =  $state['Destination']['entityid'] ?? 'UNKNOWN';


        //FIXME: also log $loopDetectionCount
        Logger::warning('LoopDetection: Only ' . $timeDelta .
            ' seconds since last SSO for this user from the SP ' . var_export($entityId, true));

        // Set the loop counter back to 0
        // $_SESSION['loopDetectionCount'] = 0;


        if (!$this->logOnly) {
            // Set the loop counter back to 0
            $_SESSION['loopDetectionCount'] = 0;

            // Save state and redirect
            $id = State::saveState($state, 'ratelimit:loop_detection');
            $url = Module::getModuleURL('ratelimit/loop_detection.php');
            $httpUtils = new Utils\HTTP();
            $httpUtils->redirectTrustedURL($url, ['StateId' => $id]);
        }

    }

}
