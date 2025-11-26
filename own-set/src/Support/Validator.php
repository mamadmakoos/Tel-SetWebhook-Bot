<?php
declare(strict_types=1);
/**
 * Maintainer: Makoos (Telegram ID 1007009569)
 * Channel: @IM_MAKOOS
 * Support: @barsam_dev
 */

namespace App\Support;

final class Validator
{
    public static function isValidTelegramId(int|string $id, bool $allowNegative = false): bool
    {
        $value = (string)$id;
        if ($value === '0') {
            return false;
        }

        $pattern = $allowNegative ? '/^-?\d+$/' : '/^\d+$/';
        return preg_match($pattern, $value) === 1;
    }

    public static function isValidToken(string $token): bool
    {
        return preg_match('/^\d+:[A-Za-z0-9_-]{20,}$/', trim($token)) === 1;
    }

    public static function isValidHttpsUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false && str_starts_with($url, 'https://');
    }
}

