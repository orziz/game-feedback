<?php

declare(strict_types=1);

return [
    // 应用标识名，用于日志区分多实例部署
    // Application identifier, used to distinguish instances in logs
    'app_name' => 'game-feedback',

    // 应用版本号，由开发者维护，仅作展示用途
    // Application version number, maintained by developers for display purposes only
    'app_version' => '0.1.1',

    // 服务端时区，影响日志与数据库时间戳的本地化输出
    // Server timezone; affects localized output of log and database timestamps
    'timezone' => 'Asia/Shanghai',

    // 允许跨域请求的来源列表（CORS），格式：['https://your-domain.com']
    // 留空时仅 allow_localhost_cors 控制本机访问
    // Allowed CORS origins, e.g. ['https://your-domain.com']
    // Leave empty to only rely on allow_localhost_cors for local access
    'cors_allowed_origins' => [],

    // 是否允许来自 localhost / 127.0.0.1 的跨域请求
    // 开发环境可设为 true；生产环境建议改为 false，防止同机其他 Web 应用跨域访问管理接口
    // Allow cross-origin requests from localhost / 127.0.0.1
    // Set true for development; set false in production to prevent other local apps from accessing admin APIs
    'allow_localhost_cors' => true,
];
