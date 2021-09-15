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
    private $whitelist;

    public function __construct(Configuration $config)
    {
        parent::__construct($config);
        $this->whitelist = $config->getArray('whitelist', []);
    }

    public function allow(string $username, string $password): string
    {
        if ($this->isIpWhiteListed($_SERVER['REMOTE_ADDR'])) {
            return UserPassBaseLimiter::PREAUTH_CONTINUE;
        }
        return parent::allow($username, $password);
    }

    public function postFailure(string $username, string $password): int
    {
        if ($this->isIpWhiteListed($_SERVER['REMOTE_ADDR'])) {
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
        return "ip-" . $_SERVER['REMOTE_ADDR'];
    }
}
