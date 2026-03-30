<?php

declare(strict_types=1);

final class TicketType
{
    const Bug        = 0;
    const Feature    = 1;
    const Suggestion = 2;
    const Other      = 3;

    private static $valid = [0, 1, 2, 3];

    /** @return int|null */
    public static function tryFrom(int $value)
    {
        return in_array($value, self::$valid, true) ? $value : null;
    }
}
