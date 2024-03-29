<?php

namespace SimpleSAML\Module\ratelimit\Limiters;

use SimpleSAML\Configuration;
use SimpleSAML\Logger;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Request;

/**
 * Limit attempts by IP address
 * @package SimpleSAML\Module\ratelimit\Limiters
 */
class IpLimiter extends UserPassBaseLimiter
{
    /**
     * @var array The IP addresses that we ignore the rate limiter for
     */
    private array $whitelist;

    private string $clientIpAddress;

    public function __construct(Configuration $config)
    {
        parent::__construct($config);
        /** @var string[] whitelist */
        $this->whitelist = $config->getOptionalArray('whitelist', []);
        $ip = Request::createFromGlobals()->getClientIp();
        if ($ip == null) {
            Logger::warning('No client ip address found. Using 127.0.0.1');
            $ip = '127.0.0.1';
        }
        $this->clientIpAddress = $ip;
    }

    public function allow(string $username, string $password): string
    {
        if ($this->isIpWhiteListed($this->clientIpAddress)) {
            return UserPassBaseLimiter::PREAUTH_CONTINUE;
        }
        return parent::allow($username, $password);
    }

    public function postFailure(string $username, string $password): int
    {
        if ($this->isIpWhiteListed($this->clientIpAddress)) {
            return 0;
        }
        return parent::postFailure($username, $password);
    }

    private function isIpWhiteListed(string $ip): bool
    {
        return IpUtils::checkIp($ip, $this->whitelist);
    }

    public function getRateLimitKey(string $username, string $password): string
    {
        return "ip-" . $this->clientIpAddress;
    }

    /**
     * @return string
     */
    public function getClientIpAddress(): ?string
    {
        return $this->clientIpAddress;
    }

    /**
     * @param string $clientIpAddress
     */
    public function setClientIpAddress(string $clientIpAddress): void
    {
        $this->clientIpAddress = $clientIpAddress;
    }
}
