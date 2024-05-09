<?php

declare(strict_types=1);

namespace SimpleSAML\Module\ratelimit\Limiters;

use SimpleSAML\Module\ratelimit\PreAuthStatusEnum;

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
     * @return \SimpleSAML\Module\ratelimit\PreAuthStatusEnum
     */
    public function allow(string $username, string $password): PreAuthStatusEnum;

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
