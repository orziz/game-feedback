<?php

declare(strict_types=1);

namespace GameFeedback\Support;

use GameFeedback\Enums\TicketSeverity;
use GameFeedback\Enums\TicketStatus;
use GameFeedback\Enums\TicketType;


/**
 * 枚举选项构建器
 */
final class EnumOptionsProvider
{
    /**
     * @param string $lang 语言标识
     * @return array{types:array<int,array{label:string,value:int}>,severities:array<int,array{label:string,value:int}>,statuses:array<int,array{label:string,value:int}>}
     */
    public static function build(string $lang): array
    {
        $normalized = strtolower($lang);
        $isEn = strpos($normalized, 'en') === 0;

        $typeLabels = $isEn
            ? ['BUG', 'Feature', 'Suggestion', 'Other']
            : ['BUG', '优化', '建议', '其他'];

        $severityLabels = $isEn
            ? ['Low', 'Medium', 'High', 'Critical']
            : ['低', '中', '高', '致命'];

        $statusLabels = $isEn
            ? ['Pending', 'In Progress', 'Resolved', 'Closed']
            : ['待处理', '处理中', '已解决', '已关闭'];

        return [
            'types' => [
                ['label' => $typeLabels[0], 'value' => TicketType::Bug],
                ['label' => $typeLabels[1], 'value' => TicketType::Feature],
                ['label' => $typeLabels[2], 'value' => TicketType::Suggestion],
                ['label' => $typeLabels[3], 'value' => TicketType::Other],
            ],
            'severities' => [
                ['label' => $severityLabels[0], 'value' => TicketSeverity::Low],
                ['label' => $severityLabels[1], 'value' => TicketSeverity::Medium],
                ['label' => $severityLabels[2], 'value' => TicketSeverity::High],
                ['label' => $severityLabels[3], 'value' => TicketSeverity::Critical],
            ],
            'statuses' => [
                ['label' => $statusLabels[0], 'value' => TicketStatus::Pending],
                ['label' => $statusLabels[1], 'value' => TicketStatus::InProgress],
                ['label' => $statusLabels[2], 'value' => TicketStatus::Resolved],
                ['label' => $statusLabels[3], 'value' => TicketStatus::Closed],
            ],
        ];
    }
}
