<?php

namespace SimpleSAML\Module\ratelimit\Limiters;

use SimpleSAML\Configuration;
use Symfony\Component\HttpFoundation\IpUtils;

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

    public function __construct(Configuration $config)
    {
        parent::__construct($config);
        /** @var string[] whitelist */
        $this->whitelist = $config->getOptionalArray('whitelist', []);
    }

    public function allow(string $username, string $password): string
    {
        if ($this->isIpWhiteListed($this->getClientIp())) {
            return UserPassBaseLimiter::PREAUTH_CONTINUE;
        }
        return parent::allow($username, $password);
    }

    public function postFailure(string $username, string $password): int
    {
        if ($this->isIpWhiteListed($this->getClientIp())) {
            return 0;
        }
        return parent::postFailure($username, $password);
    }

    private function getClientIp(): string
    {
        /** @var string */
        return $_SERVER['REMOTE_ADDR'];
    }


    private function isIpWhiteListed(string $ip): bool
    {
        return IpUtils::checkIp($ip, $this->whitelist);
    }

    public function getRateLimitKey(string $username, string $password): string
    {
        return "ip-" . $this->getClientIp();
    }
}
