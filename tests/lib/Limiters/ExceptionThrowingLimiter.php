<?php

namespace SimpleSAML\Test\Module\ratelimit\Limiters;

use Exception;
use SimpleSAML\Module\ratelimit\Limiters\UserPassLimiter;

class ExceptionThrowingLimiter implements UserPassLimiter
{

    /**
     * Called prior to verifying the credentials to determine if the attempt is allowed.
     * @param string $username The username to check
     * @param string $password The password to check
     * @return string allow|block|continue
     * @throws \Exception always thrown
     */
    public function allow(string $username, string $password): string
    {
        throw new Exception('Boom!');
    }

    /**
     * Called after a successful authentication
     * @param string $username The username to check
     * @param string $password The password to check
     * @throws \Exception always thrown
     */
    public function postSuccess(string $username, string $password): void
    {
        throw new Exception('Boom!');
    }

    /**
     * Called after a failed authentication
     * @param string $username The username to check
     * @param string $password The password to check
     * @return int The current failure count for this limit
     * @throws \Exception always thrown
     */
    public function postFailure(string $username, string $password): int
    {
        throw new Exception('Boom!');
    }
}
