<?php

declare(strict_types=1);

namespace SimpleSAML\Module\ratelimit\Auth\Source;

use Exception;
use ReflectionClass;
use SimpleSAML\Assert\Assert;
use SimpleSAML\Auth\Source;
use SimpleSAML\{Configuration, Logger, Module};
use SimpleSAML\Error\Error;
use SimpleSAML\Module\core\Auth\UserPassBase;
use SimpleSAML\Module\ratelimit\Limiters\{
    DeviceCookieLimiter,
    IpLimiter,
    PasswordStuffingLimiter,
    UsernameLimiter,
    UserPassLimiter,
};
use SimpleSAML\Store\{StoreFactory, StoreInterface};

use function get_class;
use function is_string;
use function microtime;
use function sprintf;
use function var_export;

/**
 * Auth source that rate limits user and password attempts
 * @package SimpleSAML\Module\ratelimit\Auth\Source
 */
class RateLimitUserPass extends UserPassBase
{
    /**
     * @var \SimpleSAML\Module\core\Auth\UserPassBase The auth source to handle the login
     */
    private UserPassBase $delegate;

    /**
     * @var \SimpleSAML\Module\ratelimit\Limiters\UserPassLimiter[]
     */
    private array $rateLimiters = [];

    /** @psalm-suppress MissingClassConstType */
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
        $this->delegate = $this->resolveDelegateConfig($config->getValue('delegate'));

        $storeType = Configuration::getInstance()->getOptionalString('store.type', 'phpsession');
        $storeInstance = StoreFactory::getInstance($storeType);
        Assert::implementsInterface(
            $storeInstance,
            StoreInterface::class,
            "Store other than 'phpsession' must be configured."
        );

        /** @var array[] $rateLimitersConfig */
        $rateLimitersConfig = $config->getOptionalArray('ratelimit', RateLimitUserPass::DEFAULT_CONFIG);
        foreach ($rateLimitersConfig as $rateConfig) {
            $this->rateLimiters[] = self::parseConfig($rateConfig);
        }
    }

    /**
     * @param mixed $delegate
     * @return \SimpleSAML\Module\core\Auth\UserPassBase
     */
    private function resolveDelegateConfig($delegate): UserPassBase
    {
        if (is_string($delegate)) {
            // delegate to another named authsource
            /** @var \SimpleSAML\Module\core\Auth\UserPassBase */
            $authInstance = Source::getById($delegate, UserPassBase::class);
        } elseif (is_array($delegate)) {
            $class = new ReflectionClass(Source::class);
            $method = $class->getMethod('parseAuthSource');
            /** @psalm-suppress UnusedMethodCall */
            $method->setAccessible(true);
            /** @var \SimpleSAML\Module\core\Auth\UserPassBase */
            $authInstance = $method->invokeArgs(null, [$this->getAuthId() . '-delegate', $delegate]);
        } else {
            throw new Exception('Invalid configuration for delegate. Must be string or array');
        }
        Assert::isInstanceOf($authInstance, UserPassBase::class);
        return $authInstance;
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
            } catch (Exception $e) {
                Logger::warning(sprintf(
                    'Limiter error in \'allow\' of %s; Error %s',
                    get_class($limiter),
                    $e->getMessage(),
                ));
                continue;
            }
            switch ($result) {
                case 'allow':
                    Logger::debug(sprintf('User \'%s\' login attempt allowed by %s', $username, get_class($limiter)));
                    return true;
                case 'block':
                    Logger::stats(sprintf('User \'%s\' login attempt blocked by %s', $username, get_class($limiter)));
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
            } catch (Exception $e) {
                Logger::warning(sprintf(
                    'Limiter error in \'postFailure\' of %s; Error %s',
                    get_class($limiter),
                    $e->getMessage(),
                ));
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
            } catch (Exception $e) {
                Logger::warning(sprintf(
                    'Limiter error in \'postSuccess\' of %s; Error %s',
                    get_class($limiter),
                    $e->getMessage(),
                ));
                continue;
            }
        }
    }

    /**
     * Used for validation testing
     * @return UserPassBase
     */
    public function getDelegate(): UserPassBase
    {
        return $this->delegate;
    }
}
