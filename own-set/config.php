<?php
declare(strict_types=1);
/**
 * Maintainer: Makoos (Telegram ID 1007009569)
 * Channel: @IM_MAKOOS
 * Support: @barsam_dev
 */

/**
 * فایل تنظیمات ربات
 */

$token = $token ?? null;
$admin = $admin ?? 1007009569;
$apiweb = $apiweb ?? "https://sabioweb.ir/bot.php";
$channel = $channel ?? "IM_MAKOOS";
$support = $support ?? "@barsam_dev";
$channelList = $channelList ?? [$channel];
$supportContacts = $supportContacts ?? [$support];
$adminRoles = $adminRoles ?? [$admin => 'super_admin'];

define('DATA_DIR', __DIR__ . '/data/');
define('USERS_FILE', DATA_DIR . 'users.txt');
define('VERIFIED_FILE', DATA_DIR . 'verified.txt');
define('ADMIN_MSGID_FILE', DATA_DIR . 'admin_msgid.txt');
define('STEPS_DIR', DATA_DIR . 'steps/');
define('LOG_DIR', DATA_DIR . 'logs/');
define('CACHE_DIR', DATA_DIR . 'cache/');
define('QUEUE_DIR', DATA_DIR . 'queue/');
define('CONTEXT_DIR', DATA_DIR . 'context/');
define('LOG_FILE', LOG_DIR . 'bot.log');
define('VERIFY_SSL', false);

array_map(
    static fn (string $dir) => is_dir($dir) ?: mkdir($dir, 0755, true),
    [DATA_DIR, STEPS_DIR, LOG_DIR, CACHE_DIR, QUEUE_DIR, CONTEXT_DIR]
);


define('API_KEY', $token);
define('DEFAULT_WEBHOOK_URL', $apiweb);
$adminIds = array_map('intval', array_keys($adminRoles));
define('ADMINS', $adminIds);
define('ADMIN_ROLES', $adminRoles);
define('CHANNELS', array_values($channelList));
define('SUPPORT_CONTACTS', array_values($supportContacts));

