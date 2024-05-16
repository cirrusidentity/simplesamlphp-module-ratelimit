<?php

declare(strict_types=1);

namespace SimpleSAML\Module\ratelimit\Auth\Process;

use Exception;
use SimpleSAML\Auth\ProcessingFilter;
use SimpleSAML\Auth\State;
use SimpleSAML\{Configuration, Logger, Module, Session, Utils};

use function is_int;
use function is_null;
use function sprintf;
use function time;
use function var_export;

/**
 * Give a warning to the user if we receive multiple requests in a short time.
 *
 * @package SimpleSAMLphp
 */
class LoopDetection extends ProcessingFilter
{
    /**
     * Increase loop count if less than this number of seconds since Previous SSO
     */
    private int $secondsSinceLastSso;


    /**
     * The number of loops before warning/redirecting
     */
    private int $loopsBeforeWarning;

    /**
     * Only log a warning instead of redirecting user.
     */
    private bool $logOnly;

    private ?Utils\HTTP $http = null;

    /**
     * Initialize this filter.
     *
     * @param array &$configArray Configuration information about this filter.
     * @param mixed $reserved For future use.
     */
    public function __construct(array &$configArray, $reserved)
    {
        parent::__construct($configArray, $reserved);
        $config = Configuration::loadFromArray($configArray);

        $this->secondsSinceLastSso = $config->getInteger('secondsSinceLastSso');
        $this->loopsBeforeWarning = $config->getInteger('loopsBeforeWarning');
        $this->logOnly = $config->getOptionalBoolean('logOnly', true);
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
    public function process(array &$state): void
    {
        $session = Session::getSessionFromRequest();

        /** @var mixed $previousTimestamp */
        $previousTimestamp = $state['PreviousSSOTimestamp'] ?? null;
        if (is_null($previousTimestamp)) {
            /*
             * No timestamp from the previous SSO to this SP. This is the first
             * time during this session.
             */
            $session->setData('ratelimit:loopDetection', 'Count', 0);
            return;
        } elseif (!is_int($previousTimestamp)) {
            Logger::warning('PreviousSSOTimestamp is not an int. Skipping loop detection');
            return;
        }

        $timeDelta = time() - $previousTimestamp;
        if ($timeDelta >= $this->secondsSinceLastSso) {
            // At least 10 seconds since last attempt
            $session->setData('ratelimit:loopDetection', 'Count', 0);
            return;
        }

        /** @var int $loopDetectionCount */
        $loopDetectionCount = $session->getData('ratelimit:loopDetection', 'Count');
        $loopDetectionCount++;
        Logger::debug(sprintf('LoopDetectionCount: %d', $loopDetectionCount));

        $session->setData('ratelimit:loopDetection', 'Count', $loopDetectionCount);

        /** @var string $entityId */
        $entityId =  $state['Destination']['entityid'] ?? 'UNKNOWN';

        if ($loopDetectionCount <= $this->loopsBeforeWarning) {
            if ($loopDetectionCount > 1) {
                Logger::warning(sprintf(
                    'LoopDetectionCount: %d entityId: %s',
                    $loopDetectionCount,
                    var_export($entityId, true),
                ));
            }
            return;
        }

        Logger::warning(sprintf(
            'LoopDetection: Only %d seconds since last SSO for this user from the SP %s. LoopDetectionCount %d',
            $timeDelta,
            var_export($entityId, true),
            $loopDetectionCount,
        ));

        if (!$this->logOnly) {
            // Set the loop counter back to 0
            $session->setData('ratelimit:loopDetection', 'Count', 0);
            // Save state and redirect
            $id = State::saveState($state, 'ratelimit:loop_detection');
            $url = Module::getModuleURL('ratelimit/loop_detection');
            $this->getHttp()->redirectTrustedURL($url, ['StateId' => $id]);
        }
    }

    /**
     * Used to allow tests to override
     * @return \SimpleSAML\Utils\HTTP
     */
    public function getHttp(): Utils\HTTP
    {
        if (!isset($this->http)) {
            $this->http = new Utils\HTTP();
        }
        return $this->http;
    }

    /**
     * @param \SimpleSAML\Utils\HTTP|null $http
     */
    public function setHttp(?Utils\HTTP $http): void
    {
        $this->http = $http;
    }
}
