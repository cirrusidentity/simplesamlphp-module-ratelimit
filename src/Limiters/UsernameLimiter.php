<?php

declare(strict_types=1);

namespace SimpleSAML\Module\ratelimit\Limiters;

class UsernameLimiter extends UserPassBaseLimiter
{
    public function getRateLimitKey(string $username, string $password): string
    {
        return "user-$username";
    }
}
