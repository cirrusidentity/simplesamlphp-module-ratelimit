<?php

declare(strict_types=1);

namespace SimpleSAML\Module\ratelimit;

enum PreAuthStatusEnum
{
    case ALLOW;
    case BLOCK;
    case CONTINUE;
}
