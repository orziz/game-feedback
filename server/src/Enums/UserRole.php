<?php

declare(strict_types=1);

namespace GameFeedback\Enums;

/**
 * 管理员角色常量
 */
final class UserRole
{
    /** 超级管理员（可管理用户） */
    const SuperAdmin = 'super_admin';

    /** 普通管理员 */
    const Admin = 'admin';

    /** 所有合法角色 */
    const ALL = [self::SuperAdmin, self::Admin];
}
