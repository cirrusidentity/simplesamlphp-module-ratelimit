<?php

namespace SimpleSAML\Module\ratelimit\Auth\Source;

use ReflectionClass;
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
use SimpleSAML\Store;


class RateLimitUserPass extends UserPassBase
{

    /**
     * @var UserPassBase The auth source to handle the login
     */
    private $delegate;

    /**
     * @var string The cookie name to use for device cookies
     */
    private $deviceCookieName;

    /**
     * @var UserPassLimiter[]
     */
    private $rateLimiters;

    const DEFAULT_CONFIG = [
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
     * @param array $config Configuration.
     */
    public function __construct($info, $config)
    {
        // Call the parent constructor first, as required by the interface
        parent::__construct($info, $config);
        $config = Configuration::loadFromArray(
            $config,
            'Authentication source ' . var_export($this->authId, true)
        );
        // delegate to another named authsourc has security issues since delgated authsource can be invoked by attacker
        //$this->delegate = Source::getById($config->getString('delegate'), UserPassBase::class);

        // instead make parseAuthSource accessible and call it
        $delegateConfig = $config->getArray('delegate');
        $class = new ReflectionClass(Source::class);
        $method = $class->getMethod('parseAuthSource');
        $method->setAccessible(true);
        $this->delegate = $method->invokeArgs(null, [$this->getAuthId() . '-delegate', $delegateConfig]);

        $this->deviceCookieName = $config->getString('deviceCookieName', 'deviceCookie');

        assert(Store::getInstance() !== false, "Store must be configured");
        $rateLimitersConfig = $config->getArray('ratelimit', RateLimitUserPass::DEFAULT_CONFIG);
        foreach ($rateLimitersConfig as $rateConfig) {
            $this->rateLimiters[] = self::parseConfig($rateConfig);
        }
    }

    private static function parseConfig($rateConfig): UserPassLimiter
    {
        $config = Configuration::loadFromArray($rateConfig);
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
                return new $className($config);
        }
    }

    /**
     * Performs rate limiting of a delegated authsource.
     *
     * @param string $username The username the user wrote.
     * @param string $password The password the user wrote.
     * @return array  Associative array with the user's attributes.
     */
    public function login($username, $password)
    {
        if (!$this->allowLoginAttempt($username, $password)) {
            throw new Error('WRONGUSERPASS');
        }
        try {
            $attributes = $this->delegate->login($username, $password);
        } catch (\SimpleSAML\Error\Error $e) {
            if ($e->getErrorCode() === 'WRONGUSERPASS') {
                $this->recordFailedAttempt($username, $password);
            }
            // rethrow the error so it's not lost
            throw $e;
        }
        $this->recordSuccess($username, $password);
        return $attributes;
    }

    public function getRateLimiters(): array
    {
        return $this->rateLimiters;
    }

    public function allowLoginAttempt(string $username, string $password): bool
    {
        //TODO: how to handle an exception??
        foreach ($this->rateLimiters as $limiter) {
            $result = $limiter->allow($username, $password);
            switch ($result) {
                case 'allow':
                    return true;
                case 'block':
                    return false;
                case 'continue':
                    continue;
                default:
                    Logger::warning("Unrecognized ratelimit allow() value '$result'");
            }
        }

        return true;
    }


    private function recordFailedAttempt(string $username, string $password)
    {
        //TODO: how to handle an exception??
        foreach ($this->rateLimiters as $limiter) {
            $limiter->postFailure($username, $password);
        }
    }

    private function recordSuccess(string $username, string $password)
    {
        //TODO: how to handle an exception??
        foreach ($this->rateLimiters as $limiter) {
            $limiter->postSuccess($username, $password);
        }
    }
}