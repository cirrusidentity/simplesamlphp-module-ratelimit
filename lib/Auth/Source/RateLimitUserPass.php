<?php

namespace SimpleSAML\Module\ratelimit\Auth\Source;

use ReflectionClass;
use SimpleSAML\Assert\Assert;
use SimpleSAML\Auth\Source;
use SimpleSAML\Configuration;
use SimpleSAML\Error\Error;
use SimpleSAML\Logger;
use SimpleSAML\Module;
use SimpleSAML\Module\core\Auth\UserPassBase;
use SimpleSAML\Module\ratelimit\Limiters\DeviceCookieLimiter;
use SimpleSAML\Module\ratelimit\Limiters\IpLimiter;
use SimpleSAML\Module\ratelimit\Limiters\PasswordStuffingLimiter;
use SimpleSAML\Module\ratelimit\Limiters\UsernameLimiter;
use SimpleSAML\Module\ratelimit\Limiters\UserPassLimiter;
use SimpleSAML\Store\StoreFactory;

/**
 * Auth source that rate limits user and password attempts
 * @package SimpleSAML\Module\ratelimit\Auth\Source
 */
class RateLimitUserPass extends UserPassBase
{
    /**
     * @var UserPassBase The auth source to handle the login
     */
    private UserPassBase $delegate;

    /**
     * @var UserPassLimiter[]
     */
    private array $rateLimiters;

    private const DEFAULT_CONFIG = [
        0 => [
            'device',
            'window' => 'P28D',
            'limit' => 10,
        ],
        1 => [
            'user',
            'window' => 'PT5M',
            'limit' => 20
        ]
    ];


    /**
     * Constructor for this authentication source.
     *
     * @param array $info Information about this authentication source.
     * @param array $configArray Configuration.
     */
    public function __construct(array $info, array $configArray)
    {
        // Call the parent constructor first, as required by the interface
        parent::__construct($info, $configArray);
        $config = Configuration::loadFromArray(
            $configArray,
            'Authentication source ' . var_export($this->authId, true)
        );
        // delegate to another named authsourc has security issues since delgated authsource can be invoked by attacker
        //$this->delegate = Source::getById($config->getString('delegate'), UserPassBase::class);

        // instead make parseAuthSource accessible and call it
        /** @var array $delegateConfig */
        $delegateConfig = $config->getArray('delegate');
        $class = new ReflectionClass(Source::class);
        $method = $class->getMethod('parseAuthSource');
        $method->setAccessible(true);
        /** @var UserPassBase delegate */
        $this->delegate = $method->invokeArgs(null, [$this->getAuthId() . '-delegate', $delegateConfig]);

        /** @var string $storeType */
        $storeType = Configuration::getInstance()->getString('store.type', 'phpsession');
        assert(StoreFactory::getInstance($storeType) !== false, "Store must be configured");
        /** @var array[] $rateLimitersConfig */
        $rateLimitersConfig = $config->getArray('ratelimit', RateLimitUserPass::DEFAULT_CONFIG);
        foreach ($rateLimitersConfig as $rateConfig) {
            $this->rateLimiters[] = self::parseConfig($rateConfig);
        }
    }

    /**
     * @param array $rateConfig
     * @return UserPassLimiter
     */
    private static function parseConfig(array $rateConfig): UserPassLimiter
    {
        $config = Configuration::loadFromArray($rateConfig);
        /** @var string $rateType */
        $rateType = $rateConfig[0];
        switch ($rateType) {
            case 'device':
                return new DeviceCookieLimiter($config);
            case 'ip':
                return new IpLimiter($config);
            case 'password':
                return new PasswordStuffingLimiter($config);
            case 'user':
                return new UsernameLimiter($config);
            default:
                // limiter from module
                $className = Module::resolveClass($rateType, 'UserPassLimiter');
                /** @var UserPassLimiter $obj */
                $obj = new $className($config);
                return $obj;
        }
    }

    /**
     * Performs rate limiting of a delegated authsource.
     *
     * @param string $username The username the user wrote.
     * @param string $password The password the user wrote.
     * @return array  Associative array with the user's attributes.
     * @throws Error thrown on wrong password or other errors
     */
    public function login(string $username, string $password): array
    {
        $timeStart = microtime(true);
        if (!$this->allowLoginAttempt($username, $password)) {
            throw new Error('WRONGUSERPASS');
        }
        $timeDelegateStart = microtime(true);
        try {
            $attributes = $this->delegate->login($username, $password);
            $timeDelegateEnd = microtime(true);
        } catch (Error $e) {
            $timeDelegateEnd = microtime(true);
            if ($e->getErrorCode() === 'WRONGUSERPASS') {
                $this->recordFailedAttempt($username, $password);
            }
            $this->logOverhead($timeStart, $timeDelegateStart, $timeDelegateEnd);
            // rethrow the error so it's not lost
            throw $e;
        }
        $this->recordSuccess($username, $password);
        $this->logOverhead($timeStart, $timeDelegateStart, $timeDelegateEnd);
        return $attributes;
    }

    /**
     * @param float $timeStart The time the login method started
     * @param float $timeDelegateStart The time we started delegating to another authsource
     * @param float $timeDelegateEnd The time the delegate finished or erred
     */
    private function logOverhead(float $timeStart, float $timeDelegateStart, float $timeDelegateEnd): void
    {
        $delegateTime = $timeDelegateEnd - $timeDelegateStart;
        $overHead = (microtime(true) - $timeStart) - $delegateTime;
        Logger::debug(sprintf("Timer: Rate Limit overhead %f, underlying source took %f", $overHead, $delegateTime));
    }

    /**
     * Determine if this authentication attempt should be allowed
     * @param string $username The username submitted
     * @param string $password The password submitted
     * @return bool true if it should be allowed, false if it should be blocked
     */
    public function allowLoginAttempt(string $username, string $password): bool
    {
        foreach ($this->rateLimiters as $limiter) {
            try {
                $result = $limiter->allow($username, $password);
            } catch (\Exception $e) {
                Logger::warning('Limiter error in \'allow\' of ' . get_class($limiter) . ' Error ' . $e->getMessage());
                continue;
            }
            switch ($result) {
                case 'allow':
                    Logger::debug('User \'' . $username . '\' login attempt allowed by ' . get_class($limiter));
                    return true;
                case 'block':
                    Logger::stats('User \'' . $username . '\' login attempt blocked by ' . get_class($limiter));
                    return false;
                case 'continue':
                    continue 2;
                default:
                    Logger::warning("Unrecognized ratelimit allow() value '$result'");
            }
        }

        return true;
    }

    /**
     * Record failed authentication in each limiter
     * @param string $username The user that authenticated
     * @param string $password The password used for authentication.
     */
    private function recordFailedAttempt(string $username, string $password): void
    {
        foreach ($this->rateLimiters as $limiter) {
            try {
                $limiter->postFailure($username, $password);
            } catch (\Exception $e) {
                Logger::warning(
                    'Limiter error in \'postFailure\' of ' . get_class($limiter) . ' Error ' . $e->getMessage()
                );
                continue;
            }
        }
    }

    /**
     * Record successful authentication in each limiter
     * @param string $username The user that authenticated
     * @param string $password The password used for authentication.
     */
    private function recordSuccess(string $username, string $password): void
    {
        foreach ($this->rateLimiters as $limiter) {
            try {
                $limiter->postSuccess($username, $password);
            } catch (\Exception $e) {
                Logger::warning(
                    'Limiter error in \'postSuccess\' of ' . get_class($limiter) . ' Error ' . $e->getMessage()
                );
                continue;
            }
        }
    }
}
