<?php

declare(strict_types=1);

namespace GameFeedback\Support;

final class AppInputSanitizer
{
    public function sanitizeSingleLine(string $value, int $maxLength): string
    {
        $clean = str_replace("\0", '', $value);
        $clean = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $clean) ?? '';
        $clean = trim(preg_replace('/\s+/u', ' ', $clean) ?? '');

        if ($this->stringLength($clean) > $maxLength) {
            Responder::error('PAYLOAD_TOO_LARGE', '输入内容超出长度限制。', 422);
        }

        return $clean;
    }

    public function sanitizeText(string $value, int $maxLength): string
    {
        $clean = str_replace("\0", '', $value);
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $clean) ?? '';
        $clean = trim($clean);

        if ($this->stringLength($clean) > $maxLength) {
            Responder::error('PAYLOAD_TOO_LARGE', '输入内容超出长度限制。', 422);
        }

        return $clean;
    }

    public function stringLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }

        return strlen($value);
    }

    public function parseInt(string $value, int $min, int $max): int
    {
        $number = filter_var($value, FILTER_VALIDATE_INT);
        if ($number === false) {
            return $min;
        }

        $number = (int)$number;
        if ($number < $min) {
            return $min;
        }

        if ($number > $max) {
            return $max;
        }

        return $number;
    }

    public function isValidTicketNo(string $ticketNo): bool
    {
        return preg_match('/^FB\d{8}[A-F0-9]{6}$/', $ticketNo) === 1;
    }
}
