<?php

declare(strict_types=1);

namespace GameFeedback\Enums;

/**
 * 工单严重程度枚举
 *
 * 对应 feedback_tickets.severity 字段，仅 BUG 类型工单有意义
 */
final class TicketSeverity
{
    /** 低 */
    const Low      = 0;
    /** 中 */
    const Medium   = 1;
    /** 高 */
    const High     = 2;
    /** 致命 */
    const Critical = 3;

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
