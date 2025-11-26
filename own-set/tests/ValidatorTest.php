<?php
declare(strict_types=1);
/**
 * Maintainer: Makoos (Telegram ID 1007009569)
 * Channel: @IM_MAKOOS
 * Support: @barsam_dev
 */

use App\Support\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    public function testValidTelegramToken(): void
    {
        $this->assertTrue(Validator::isValidToken('123456789:ABCdefGhIJKlmNoPQRstuVWXyz-_12345'));
    }

    public function testInvalidTelegramToken(): void
    {
        $this->assertFalse(Validator::isValidToken('invalid-token'));
    }

    public function testAcceptsHttpsWebhook(): void
    {
        $this->assertTrue(Validator::isValidHttpsUrl('https://example.com/bot.php'));
    }

    public function testRejectsHttpWebhook(): void
    {
        $this->assertFalse(Validator::isValidHttpsUrl('http://example.com/bot.php'));
    }
}

