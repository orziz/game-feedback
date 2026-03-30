<?php

declare(strict_types=1);

final class TicketSeverity
{
    const Low      = 0;
    const Medium   = 1;
    const High     = 2;
    const Critical = 3;

    private static $valid = [0, 1, 2, 3];

    /** @return int|null */
    public static function tryFrom(int $value)
    {
        return in_array($value, self::$valid, true) ? $value : null;
    }
}
