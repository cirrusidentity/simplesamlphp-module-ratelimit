<?php

namespace SimpleSAML\Module\ratelimit\Limiters;

use SimpleSAML\Configuration;
use SimpleSAML\Logger;
use SimpleSAML\Store;
use SimpleSAML\Utils\HTTP;

class DeviceCookieLimiter extends UserPassBaseLimiter
{

    /**
     * @var string The cookie name to use for device cookies
     */
    private $deviceCookieName;

    public function __construct(Configuration $config)
    {
        parent::__construct($config);
        // Device Cookie has a long window to store the cookie value
        $this->windowDuration = $config->getString('window', 'P28D');
        $this->deviceCookieName = $config->getString('deviceCookieName', 'deviceCookie');
    }

    public function getRateLimitKey(string $username, string $password): string
    {
        return "device-" . $this->checkForDeviceCookie();
    }

    public function allow(string $username, string $password): string
    {
        if (!$this->hasDeviceCookieSet()) {
            return UserPassBaseLimiter::PREAUTH_CONTINUE;
        }
        $key = $this->getRateLimitKey($username, $password);
        $store = Store::getInstance();
        $ret = $store->get('array', "ratelimit-$key");
        if ($ret === null) {
            return UserPassBaseLimiter::PREAUTH_CONTINUE;
        }
        return $ret['user'] === $username ? UserPassBaseLimiter::PREAUTH_ALLOW : UserPassBaseLimiter::PREAUTH_CONTINUE;
    }

    public function postFailure(string $username, string $password): int
    {
        if (!$this->hasDeviceCookieSet()) {
            return 0;
        }
        $key = $this->getRateLimitKey($username, $password);
        $store = Store::getInstance();
        $ret = $store->get('array', "ratelimit-$key");
        if ($ret === null || $ret['user'] !== $username) {
            return 0;
        }
        // Only track attempts for device cookies that exist and match the user
        $ret['count']++;
        if ($ret['count'] >= $this->limit) {
            Logger::debug('Too many failed attempts for device cookie \'' . $this->checkForDeviceCookie() . '\'');
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
        $store = Store::getInstance();
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

    private function setDeviceCookie(?string $cookieValue)
    {
        $params = array(
            'lifetime' => $this->window,
            'path' => Configuration::getConfig()->getBasePath(),
            'secure'   => Configuration::getConfig()->getBoolean('session.cookie.secure', false),
        );

        HTTP::setCookie(
            $this->deviceCookieName,
            $cookieValue,
            $params
        );
    }

    private function checkForDeviceCookie(): string
    {
        return $_COOKIE[$this->deviceCookieName];
    }

    private function hasDeviceCookieSet(): string
    {
        return array_key_exists($this->deviceCookieName, $_COOKIE);
    }
}
