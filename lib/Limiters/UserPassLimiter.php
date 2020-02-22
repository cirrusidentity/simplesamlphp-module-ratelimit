<?php
namespace SimpleSAML\Module\ratelimit\Limiters;

/**
 * Allow limiting of user password authentications
 * @package SimpleSAML\Module\ratelimit
 */
interface UserPassLimiter
{

    /**
     * Called prior to verifying the credentials to determine if the attempt is allowed.
     * @param string $username The username to check
     * @param string $password The password to check
     * @return string allow|block|continue
     */
    public function allow(string $username, string $password): string;

    /**
     * Called after a successful authentication
     * @param string $username The username to check
     * @param string $password The password to check
     */
    public function postSuccess(string $username, string $password): void;

    /**
     * Called after a failed authentication
     * @param string $username The username to check
     * @param string $password The password to check
     * @return int The current failure count for this limit
     */
    public function postFailure(string $username, string $password): int;
}
