<?php

declare(strict_types=1);

namespace SimpleSAML\Test\Module\ratelimit\Utils;

use SimpleSAML\Module\ratelimit\Limiters\UserPassBaseLimiter;

class SampleLimiter extends UserPassBaseLimiter
{
    public function getRateLimitKey(string $username, string $password): string
    {
        return 'sample-' . $username[0];
    }

    public function determineWindowExpiration(int $time): int
    {
        return parent::determineWindowExpiration($time);
    }
}
