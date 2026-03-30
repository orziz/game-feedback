<?php

declare(strict_types=1);

namespace GameFeedback\Enums;

/**
 * 工单状态枚举
 *
 * 对应 feedback_tickets.status 字段
 */
final class TicketStatus
{
    /** 待处理 */
    const Pending    = 0;
    /** 处理中 */
    const InProgress = 1;
    /** 已解决 */
    const Resolved   = 2;
    /** 已关闭 */
    const Closed     = 3;

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
