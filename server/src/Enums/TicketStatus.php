<?php

declare(strict_types=1);

final class TicketStatus
{
    const Pending    = 0;
    const InProgress = 1;
    const Resolved   = 2;
    const Closed     = 3;

    private static $valid = [0, 1, 2, 3];

    /** @return int|null */
    public static function tryFrom(int $value)
    {
        return in_array($value, self::$valid, true) ? $value : null;
    }
}
