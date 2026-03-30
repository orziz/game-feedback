<?php

declare(strict_types=1);

namespace GameFeedback\Enums;

/**
 * 反馈类型枚举
 *
 * 对应 feedback_tickets.type 字段
 */
final class TicketType
{
    /** BUG */
    const Bug        = 0;
    /** 优化/功能 */
    const Feature    = 1;
    /** 建议 */
    const Suggestion = 2;
    /** 其他 */
    const Other      = 3;

    private static $valid = [0, 1, 2, 3];

    /**
     * 尝试将整数转为合法枚举值
     *
     * @param int $value 待验证的整数
     * @return int|null 合法时返回原值，否则 null
     */
    public static function tryFrom(int $value)
    {
        return in_array($value, self::$valid, true) ? $value : null;
    }
}
