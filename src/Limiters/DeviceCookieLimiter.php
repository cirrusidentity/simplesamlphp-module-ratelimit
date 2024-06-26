<?php

declare(strict_types=1);

namespace SimpleSAML\Module\ratelimit\Limiters;

use SimpleSAML\{Configuration, Logger};
use SimpleSAML\Module\ratelimit\PreAuthStatusEnum;
use SimpleSAML\Utils\HTTP;

use function sprintf;
use function time;

class DeviceCookieLimiter extends UserPassBaseLimiter
{
    /**
     * @var string The cookie name to use for device cookies
     */
    private string $deviceCookieName;

    private ?HTTP $http = null;

    public function __construct(Configuration $config)
    {
        // Device Cookie has a long window to store the cookie value
        parent::__construct($config, 'P28D');
        $this->deviceCookieName = $config->getOptionalString('deviceCookieName', 'deviceCookie');
    }

    public function getRateLimitKey(string $username, string $password): string
    {
        return "device-" . $this->checkForDeviceCookie();
    }

    public function allow(string $username, string $password): PreAuthStatusEnum
    {
        if (!$this->hasDeviceCookieSet()) {
            return PreAuthStatusEnum::CONTINUE;
        }
        $key = $this->getRateLimitKey($username, $password);
        /** @var array|null $ret */
        $ret = $this->getStore()->get('array', "ratelimit-$key");
        if ($ret === null) {
            return PreAuthStatusEnum::CONTINUE;
        }
        return $ret['user'] === $username ? PreAuthStatusEnum::ALLOW : PreAuthStatusEnum::CONTINUE;
    }

    public function postFailure(string $username, string $password): int
    {
        if (!$this->hasDeviceCookieSet()) {
            return 0;
        }

        $key = $this->getRateLimitKey($username, $password);
        $store = $this->getStore();
        /** @var array{count: int, user: string}|null $ret */
        $ret = $store->get('array', "ratelimit-$key");
        if ($ret === null || $ret['user'] !== $username) {
            return 0;
        }
        // Only track attempts for device cookies that exist and match the user
        $ret['count']++;
        if ($ret['count'] >= $this->limit) {
            Logger::debug(sprintf(
                'Too many failed attempts for device cookie \'%s\'',
                $this->checkForDeviceCookie(),
            ));
            $store->delete('array', "ratelimit-$key");
            $this->setDeviceCookie(null);
        } else {
            $store->set('array', "ratelimit-$key", $ret, $this->determineWindowExpiration(time()));
        }
        return $ret['count'];
    }

    /**
     * Called after a successful authentication
     * @param string $username The username to check
     * @param string $password The password to check
     */
    public function postSuccess(string $username, string $password): void
    {
        $store = $this->getStore();
        // Clear old cookie from store
        if ($this->hasDeviceCookieSet()) {
            $key = $this->getRateLimitKey($username, $password);
            $store->delete('array', "ratelimit-$key");
        }
        $value = [
          'user' => $username,
          'count' => 0,
        ];
        $cookieId = bin2hex(openssl_random_pseudo_bytes(16));
        $key = 'device-' . $cookieId;
        $store->set('array', "ratelimit-$key", $value, $this->determineWindowExpiration(time()));
        $this->setDeviceCookie($cookieId);
    }

    private function setDeviceCookie(?string $cookieValue): void
    {
        $params = array(
            'lifetime' => $this->window,
            'path' => Configuration::getConfig()->getBasePath(),
            'secure' => Configuration::getConfig()->getOptionalBoolean('session.cookie.secure', false),
        );

        $this->getHttp()->setCookie(
            $this->deviceCookieName,
            $cookieValue,
            $params
        );
    }

    private function checkForDeviceCookie(): string
    {
        /**
         * @var string
         * @psalm-suppress PossiblyInvalidArrayOffset
         */
        return $_COOKIE[$this->deviceCookieName];
    }

    private function hasDeviceCookieSet(): bool
    {
        return array_key_exists($this->deviceCookieName, $_COOKIE);
    }

    /**
     * Used to allow tests to override
     * @return HTTP
     */
    public function getHttp(): HTTP
    {
        if (!isset($this->http)) {
            $this->http = new HTTP();
        }
        return $this->http;
    }

    /**
     * @param ?HTTP $http
     */
    public function setHttp(?HTTP $http): void
    {
        $this->http = $http;
    }
}
