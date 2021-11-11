<?php

declare(strict_types=1);

namespace SimpleSAML\Module\loginloopdetection\Auth\Process;

use SimpleSAML\Assert\Assert;
use SimpleSAML\Auth;
use SimpleSAML\Configuration;
use SimpleSAML\Logger;
use SimpleSAML\Module;
use SimpleSAML\Utils;

/**
 * Give a warning to the user if we receive multiple requests in a short time.
 *
 * @package SimpleSAMLphp
 */
class LoopDetection extends Auth\ProcessingFilter
{

    /**
     * Number of seconds since PriviousSSO.
     * @var integer
     */
    private $secondssincelastsso;


    /**
     * The number of loops before warning.
     * @var integer
     */
    private $loopsbeforewarning;

    /**
     * Only log a warning.
     * @var boolean
     */
    private $logonly;


    public function __construct(&$config, $reserved)
    {
        Logger::info('LoopDetectionConfig1: ' . var_dump($config));
        parent::__construct($config, $reserved);
        $config = Configuration::loadFromArray($config);

        Logger::info('LoopDetectionConfig2: ' . var_dump($config));

        $this->secondssincelastsso = $config->getInteger('secondssincelastsso');
        $this->attributes = $config->getInteger('loopsbeforewarning');
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
            $_SESSION['loopDetectionCount'] = 0;
            return;
        }

        $timeDelta = time() - $state['PreviousSSOTimestamp'];
        if ($timeDelta >= $this->secondssincelastsso) {
            // At least 10 seconds since last attempt
            return;
        }

        $loopDetectionCount = $_SESSION['loopDetectionCount'] + 1;

        Logger::warning('LoopDetectionCount: ' . $loopDetectionCount);

        $_SESSION['loopDetectionCount'] = $loopDetectionCount;

        if ($loopDetectionCount <= $this->loopsbeforewarning) {
            return;
        }


        if (array_key_exists('Destination', $state) && array_key_exists('entityid', $state['Destination'])) {
            $entityId = $state['Destination']['entityid'];
        } else {
            $entityId = 'UNKNOWN';
        }

        Logger::warning('LoopDetection: Only ' . $timeDelta .
            ' seconds since last SSO for this user from the SP ' . var_export($entityId, true));

        // Set the loop counter back to 0
        $_SESSION['loopDetectionCount'] = 0;


        if ($logonly == FALSE) {
            // Save state and redirect
            $id = Auth\State::saveState($state, 'loginloopdetection:loop_detection');
            $url = Module::getModuleURL('loginloopdetection/loop_detection.php');
            $httpUtils = new Utils\HTTP();
            $httpUtils->redirectTrustedURL($url, ['StateId' => $id]);
        }

    }

}
